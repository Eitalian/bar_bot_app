<?php

namespace App\UI\Http\Actions\Orders;

use App\Data\Orders\CancelOrderData;
use App\Exceptions\OrderAlreadyProcessedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class CancelOrderAction
{
    public function __invoke(int $id): JsonResponse
    {
        try {
            $order = Bus::dispatch(new CancelOrderData(orderId: $id));
        } catch (OrderAlreadyProcessedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($order);
    }

    public function fromTelegram(Nutgram $bot, string $id): void
    {
        try {
            $order = Bus::dispatch(new CancelOrderData(orderId: (int) $id));
        } catch (OrderAlreadyProcessedException) {
            $bot->answerCallbackQuery(text: 'Заказ уже обработан');
            return;
        }

        $bot->answerCallbackQuery();
        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());

        $recipe = $order->recipe;
        $bot->sendMessage(
            text: "❌ Заказ на {$recipe->name_ru} отклонён 😔",
            chat_id: $order->user->telegram_id,
        );
    }
}
