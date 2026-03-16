<?php

namespace App\Telegram\Handlers;

use App\Models\Recipe;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class RecipeHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $recipe = Recipe::with('recipeIngredients', 'tags')->find($id);

        if (! $recipe) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
        }

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🔙 Назад', callback_data: 'search:back'),
            );

        $bot->editMessageText(
            text: $recipe->toTelegramMessage(),
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );

        $bot->answerCallbackQuery();
    }
}
