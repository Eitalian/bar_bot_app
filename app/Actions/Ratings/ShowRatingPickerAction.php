<?php

namespace App\Actions\Ratings;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ShowRatingPickerAction
{
    public function fromTelegram(Nutgram $bot, string $id): void
    {
        $bot->answerCallbackQuery();

        $keyboard = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make('⭐1', callback_data: "recipe:{$id}:rate:1"),
            InlineKeyboardButton::make('⭐2', callback_data: "recipe:{$id}:rate:2"),
            InlineKeyboardButton::make('⭐3', callback_data: "recipe:{$id}:rate:3"),
            InlineKeyboardButton::make('⭐4', callback_data: "recipe:{$id}:rate:4"),
            InlineKeyboardButton::make('⭐5', callback_data: "recipe:{$id}:rate:5"),
        );

        $bot->editMessageReplyMarkup(reply_markup: $keyboard);
    }
}
