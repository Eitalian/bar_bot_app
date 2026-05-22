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
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class SessionAction
{
    public function __construct(
        private readonly GetActiveSessionHandler $handler,
        private readonly Bar $bar,
        private readonly BarSchedule $schedule,
    ) {}

    public function __invoke(Request $request, int $barId): JsonResponse|Response
    {
        if ($barId !== $this->bar->id) {
            abort(404);
        }

        $session = $this->handler->handle();

        return $session !== null
            ? response()->json($session)
            : response()->noContent();
    }

    public function fromTelegram(Nutgram $bot): void
    {
        $session = $this->handler->handle();
        $now = CarbonImmutable::now();
        $canManage = auth()->user()?->role->canManage() ?? false;

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
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make(
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
