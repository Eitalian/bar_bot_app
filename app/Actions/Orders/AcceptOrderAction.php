<?php

namespace App\Actions\Orders;

use App\Data\Orders\AcceptOrderData;
use App\Exceptions\OrderAlreadyProcessedException;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class AcceptOrderAction
{
    public function fromTelegram(Nutgram $bot, string $id, int $n): void
    {
        try {
            $order = Bus::dispatch(new AcceptOrderData(
                orderId:  (int) $id,
                quantity: $n,
            ));
        } catch (OrderAlreadyProcessedException) {
            $bot->answerCallbackQuery(text: 'Заказ уже обработан');
            return;
        }

        // Убрать кнопки из уведомления бармена
        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());

        // Уведомить гостя
        $recipe = $order->recipe;
        $bot->sendMessage(
            text:    "✅ Заказ принят! {$recipe->name_ru} ×{$n} — уже готовим 🍸",
            chat_id: $order->user->telegram_id,
        );
    }
}
