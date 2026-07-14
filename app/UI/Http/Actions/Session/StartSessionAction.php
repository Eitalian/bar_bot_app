<?php

namespace App\UI\Http\Actions\Session;

use App\Data\Session\StartSessionData;
use App\Exceptions\BarClosedException;
use App\Models\Bar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;

final class StartSessionAction
{
    public function __construct(private readonly Bar $bar) {}

    public function __invoke(Request $request, int $barId): JsonResponse
    {
        if ($barId !== $this->bar->id) {
            abort(404);
        }

        try {
            $session = Bus::dispatch(new StartSessionData());
        } catch (BarClosedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($session, 201);
    }

    public function fromTelegram(Nutgram $bot): void
    {
        try {
            Bus::dispatch(new StartSessionData());
            $bot->answerCallbackQuery(text: '✅ Сессия открыта');
        } catch (BarClosedException) {
            $bot->answerCallbackQuery(text: '🚫 Бар закрыт');
        }
    }
}
