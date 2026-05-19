<?php

namespace App\Actions\Session;

use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\Bar;
use App\Services\BarSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

final class SessionAction
{
    public function __construct(
        private readonly GetActiveSessionHandler $handler,
        private readonly Bar $bar,
        private readonly BarSchedule $schedule,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse|Response
    {
        if ($id !== $this->bar->id) {
            abort(404);
        }

        $session = $this->handler->handle();

        if ($session === null) {
            return response()->noContent();
        }

        return response()->json($session);
    }

    public function fromTelegram(Nutgram $bot): void
    {
        $session = $this->handler->handle();
        $now = CarbonImmutable::now();
        $canManage = $bot->user()
            ? auth()->user()?->role->canManage() ?? false
            : false;

        if ($session !== null) {
            $bot->sendMessage(
                text: sprintf(
                    "🍸 *Сессия открыта*\nС %s\nЗакроется в %s",
                    $session->started_at->format('H:i'),
                    $this->schedule->expectedEndAt($session->started_at)->format('H:i'),
                ),
                parse_mode: ParseMode::MARKDOWN,
            );

            return;
        }

        if (! $canManage) {
            $bot->sendMessage(text: '🍸 Сессия не открыта.');

            return;
        }

        if ($this->schedule->canOpenAt($now)) {
            $bot->sendMessage(
                text: '🍸 Бар работает. Открыть сессию?',
                reply_markup: \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
                    ->addRow(\SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                        text: '🟢 Старт',
                        callback_data: 'session:start',
                    )),
            );

            return;
        }

        $bot->sendMessage(text: sprintf(
            "🍸 Бар закрыт. Работает с %s до %s (последний старт за %d минут до закрытия).",
            $this->bar->workStart,
            $this->bar->workEnd,
            $this->bar->openCutoffMinutes,
        ));
    }
}
