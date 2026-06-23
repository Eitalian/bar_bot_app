<?php

namespace App\UI\Telegram\Conversations;

use App\UI\Http\Actions\Favorites\ListFavoritesAction;
use App\UI\Http\Actions\Search\GetRecipeAction;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

final class ListFavoritesConversation extends Conversation
{
    protected int $page = 1;

    protected array $pageRecipeIds = [];

    public function start(Nutgram $bot): void
    {
        $favoritesPage = app(ListFavoritesAction::class)->fromTelegram($bot, 1);

        if ($favoritesPage->isEmpty()) {
            $bot->sendMessage('У тебя пока нет избранных рецептов 🤍');
            $this->end();

            return;
        }

        $this->page = 1;
        $this->pageRecipeIds = $favoritesPage->items->pluck('id')->toArray();
        $this->next('handleInput');
    }

    public function handleInput(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data;

        if ($callbackData === 'favorites:prev') {
            $this->page--;
            $favoritesPage = app(ListFavoritesAction::class)->fromTelegram($bot, $this->page, true);
            $this->pageRecipeIds = $favoritesPage->items->pluck('id')->toArray();
            $this->next('handleInput');

            return;
        }

        if ($callbackData === 'favorites:next') {
            $this->page++;
            $favoritesPage = app(ListFavoritesAction::class)->fromTelegram($bot, $this->page, true);
            $this->pageRecipeIds = $favoritesPage->items->pluck('id')->toArray();
            $this->next('handleInput');

            return;
        }

        if ($callbackData === 'browse:back') {
            $bot->answerCallbackQuery();
            $this->end();

            return;
        }

        if ($callbackData === 'noop') {
            $bot->answerCallbackQuery();
            $this->next('handleInput');

            return;
        }

        $text = trim($bot->message()?->text ?? '');
        if (ctype_digit($text)) {
            $num = (int) $text;
            $count = count($this->pageRecipeIds);

            if ($num >= 1 && $num <= $count) {
                $recipeId = $this->pageRecipeIds[$num - 1];
                app(GetRecipeAction::class)->fromTelegram($bot, $recipeId);
                $this->end();

                return;
            }
        }

        $bot->sendMessage('Введи число от 1 до ' . count($this->pageRecipeIds) . ' или используй кнопки навигации.');
        $this->next('handleInput');
    }
}
