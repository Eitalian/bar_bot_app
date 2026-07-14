<?php

namespace App\UI\Http\Actions\Orders;

use App\Data\Orders\AcceptOrderData;
use App\Exceptions\OrderAlreadyProcessedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class AcceptOrderAction
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        try {
            $order = Bus::dispatch(new AcceptOrderData(
                orderId: $id,
                quantity: (int) $request->input('quantity', 1),
            ));
        } catch (OrderAlreadyProcessedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($order);
    }

    public function fromTelegram(Nutgram $bot, string $id, int $n): void
    {
        try {
            $order = Bus::dispatch(new AcceptOrderData(
                orderId: (int) $id,
                quantity: $n,
            ));
        } catch (OrderAlreadyProcessedException) {
            $bot->answerCallbackQuery(text: 'Заказ уже обработан');
            return;
        }

        $bot->answerCallbackQuery();
        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());

        $recipe = $order->recipe;
        $bot->sendMessage(
            text: "✅ Заказ принят! {$recipe->name_ru} ×{$n} — уже готовим 🍸",
            chat_id: $order->user->telegram_id,
        );
    }
}
