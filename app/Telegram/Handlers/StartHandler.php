<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class StartHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $name = $bot->user()?->first_name ?? 'друг';

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🔍 Поиск по названию', callback_data: 'cmd:search'),
                InlineKeyboardButton::make('🧪 По ингредиентам', callback_data: 'cmd:ingredients'),
            )
            ->addRow(
                InlineKeyboardButton::make('🎛 Фильтры', callback_data: 'cmd:filter'),
                InlineKeyboardButton::make('📦 Инвентарь', callback_data: 'inventory:show'),
            );

        $bot->sendMessage(
            text: "👋 Привет, {$name}!\n\n"
                . "Я помогу найти рецепт коктейля по:\n"
                . "• 📝 Названию\n"
                . "• 🧪 Ингредиентам\n"
                . "• 🥃 Типу бокала\n"
                . "• 💪 Крепости\n"
                . "• 📏 Объёму\n"
                . "• 🏷 Тегам\n\n"
                . 'В базе *203 рецепта*. Что ищем?',
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );
    }
}
