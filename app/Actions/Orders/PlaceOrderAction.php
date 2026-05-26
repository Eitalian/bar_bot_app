<?php

namespace App\Actions\Orders;

use App\Data\Orders\PlaceOrderData;
use App\Enums\UserRole;
use App\Exceptions\NoActiveSessionException;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class PlaceOrderAction
{
    public function fromTelegram(Nutgram $bot, string $id): void
    {
        try {
            $order = Bus::dispatch(new PlaceOrderData(
                recipeId: $id,
                userId:   $bot->userId(),
            ));
        } catch (NoActiveSessionException) {
            $bot->answerCallbackQuery(text: '🚫 Нет активной сессии');
            return;
        }

        $order->load('recipe', 'user');

        $bot->answerCallbackQuery(text: 'Заказ отправлен! 🍸');
        $bot->editMessageReplyMarkup(
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('🔙 К поиску',   callback_data: 'browse:back'),
                    InlineKeyboardButton::make('📋 Мои заказы', callback_data: 'orders:my'),
                ),
        );

        $recipe   = $order->recipe;
        $guest    = $order->user;
        $managers = User::whereIn('role', [UserRole::Bartender->value, UserRole::Owner->value])->get();

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('✅ Все (×5)', callback_data: "order:qty:{$order->id}:5"),
                InlineKeyboardButton::make('✅ ×4',       callback_data: "order:qty:{$order->id}:4"),
                InlineKeyboardButton::make('✅ ×3',       callback_data: "order:qty:{$order->id}:3"),
                InlineKeyboardButton::make('✅ ×2',       callback_data: "order:qty:{$order->id}:2"),
                InlineKeyboardButton::make('✅ ×1',       callback_data: "order:qty:{$order->id}:1"),
            )
            ->addRow(
                InlineKeyboardButton::make('❌ Отказать', callback_data: "order:cancel:{$order->id}"),
            );

        foreach ($managers as $manager) {
            try {
                $bot->sendMessage(
                    text: "🍹 *Новый заказ*\n\nКоктейль: {$recipe->name_ru}\nГость: {$guest->first_name}" .
                          ($guest->username ? " (@{$guest->username})" : ''),
                    chat_id:      $manager->telegram_id,
                    parse_mode:   'Markdown',
                    reply_markup: $keyboard,
                );
            } catch (\Throwable) {
                // Не прерываем рассылку если один из менеджеров заблокировал бота
            }
        }
    }
}
