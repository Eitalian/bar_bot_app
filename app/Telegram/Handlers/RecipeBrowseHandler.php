<?php

namespace App\Telegram\Handlers;

use App\Handlers\Search\GetRecipeHandler;
use App\Services\BrowseContext;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class RecipeBrowseHandler
{
    public function __construct(
        private GetRecipeHandler $recipeHandler,
        private BrowseContext $browseContext,
    ) {}

    public function __invoke(Nutgram $bot, string $browseKey, string $pos): void
    {
        $position = (int) $pos;
        $ids = $this->browseContext->get($browseKey);

        if ($ids === null) {
            $bot->answerCallbackQuery(text: '🔄 Поиск устарел, начните заново');

            return;
        }

        $id = $ids[$position] ?? null;

        if ($id === null) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
        }

        $recipe = $this->recipeHandler->handle($id);

        if (! $recipe) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
        }

        $keyboard = InlineKeyboardMarkup::make();

        $nav = [];
        if ($position > 0) {
            $nav[] = InlineKeyboardButton::make(
                '◀️ Пред.',
                callback_data: "recipe:browse:{$browseKey}:" . ($position - 1),
            );
        }
        if ($position < count($ids) - 1) {
            $nav[] = InlineKeyboardButton::make(
                '▶️ След.',
                callback_data: "recipe:browse:{$browseKey}:" . ($position + 1),
            );
        }
        if (! empty($nav)) {
            $keyboard->addRow(...$nav);
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('🔙 К поиску', callback_data: 'browse:back'),
        );

        $keyboard->addRow(
            InlineKeyboardButton::make('🛒 Заказать', callback_data: 'noop'),
            InlineKeyboardButton::make('🍴 Форкнуть', callback_data: 'noop'),
        );

        $bot->editMessageText(
            text: $recipe->toTelegramMessage(),
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );

        $bot->answerCallbackQuery();
    }
}
