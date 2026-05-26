<?php

namespace App\Actions\Orders;

use App\Data\Orders\CancelOrderData;
use App\Exceptions\OrderAlreadyProcessedException;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class CancelOrderAction
{
    public function fromTelegram(Nutgram $bot, string $id): void
    {
        try {
            $order = Bus::dispatch(new CancelOrderData(orderId: (int) $id));
        } catch (OrderAlreadyProcessedException) {
            $bot->answerCallbackQuery(text: 'Заказ уже обработан');
            return;
        }

        // Убрать кнопки из уведомления бармена
        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());

        // Уведомить гостя
        $recipe = $order->recipe;
        $bot->sendMessage(
            text:    "❌ Заказ на {$recipe->name_ru} отклонён 😔",
            chat_id: $order->user->telegram_id,
        );
    }
}
