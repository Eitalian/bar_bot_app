<?php

namespace App\Actions\Orders;

use App\Data\Orders\PlaceOrderData;
use App\Enums\UserRole;
use App\Exceptions\NoActiveSessionException;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class PlaceOrderAction
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = auth()->user();

        try {
            $order = Bus::dispatch(new PlaceOrderData(
                recipeId: (string) $request->input('recipe_id'),
                userId:   $authUser->id,
                quantity: (int) $request->input('quantity', 1),
            ));
        } catch (NoActiveSessionException) {
            return response()->json(['message' => 'Нет активной сессии'], 422);
        }

        $order->load('recipe', 'user');
        $this->notifyManagers(app(Nutgram::class), $order);

        return response()->json($order, 201);
    }

    // Shows quantity picker (1-5) — entry point when guest taps "Заказать"
    public function fromTelegram(Nutgram $bot, string $id): void
    {
        $keyboard = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make('1', callback_data: "recipe:order:{$id}:1"),
            InlineKeyboardButton::make('2', callback_data: "recipe:order:{$id}:2"),
            InlineKeyboardButton::make('3', callback_data: "recipe:order:{$id}:3"),
            InlineKeyboardButton::make('4', callback_data: "recipe:order:{$id}:4"),
            InlineKeyboardButton::make('5', callback_data: "recipe:order:{$id}:5"),
        )->addRow(
            InlineKeyboardButton::make('🔙 Назад', callback_data: "recipe:show:{$id}"),
        );

        $bot->editMessageReplyMarkup(reply_markup: $keyboard);
        $bot->answerCallbackQuery(text: 'Выбери количество');
    }

    // Places order after guest picks quantity
    public function confirm(Nutgram $bot, string $id, int $qty): void
    {
        try {
            $order = Bus::dispatch(new PlaceOrderData(
                recipeId: $id,
                userId:   $bot->userId(),
                quantity: $qty,
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

        $this->notifyManagers($bot, $order);
    }

    private function notifyManagers(Nutgram $bot, \App\Models\Order $order): void
    {
        $recipe   = $order->recipe;
        $guest    = $order->user;
        $managers = User::whereIn('role', [UserRole::Bartender->value, UserRole::Owner->value])->get();

        $keyboard = $this->buildAcceptKeyboard($order);

        foreach ($managers as $manager) {
            try {
                $bot->sendMessage(
                    text: "🍹 *Новый заказ ×{$order->quantity}*\n\nКоктейль: {$recipe->name_ru}\nГость: {$guest->first_name}" .
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

    private function buildAcceptKeyboard(\App\Models\Order $order): InlineKeyboardMarkup
    {
        $buttons = [];
        for ($i = $order->quantity; $i >= 1; $i--) {
            $label     = $i === $order->quantity ? "✅ Все (×{$i})" : "✅ ×{$i}";
            $buttons[] = InlineKeyboardButton::make($label, callback_data: "order:qty:{$order->id}:{$i}");
        }

        return InlineKeyboardMarkup::make()
            ->addRow(...$buttons)
            ->addRow(InlineKeyboardButton::make('❌ Отказать', callback_data: "order:cancel:{$order->id}"));
    }
}
