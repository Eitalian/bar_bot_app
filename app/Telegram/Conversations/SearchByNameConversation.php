<?php

namespace App\Telegram\Conversations;

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use App\Services\BrowseContext;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SearchByNameConversation extends Conversation
{
    protected const PER_PAGE = 5;

    protected ?string $query = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage('🔍 Введите название коктейля (или его часть):');
        $this->next('handleQuery');
    }

    public function handleQuery(Nutgram $bot): void
    {
        $this->query = trim($bot->message()->text ?? '');

        if (empty($this->query)) {
            $bot->sendMessage('❌ Введите хотя бы один символ.');
            $this->next('handleQuery');

            return;
        }

        $this->showResults($bot);
        $this->end();
    }

    private function showResults(Nutgram $bot): void
    {
        $data = new SearchRecipesData(
            q: $this->query,
            perPage: self::PER_PAGE,
        );

        $results = app(SearchRecipesHandler::class)->handle($data);

        if ($results->isEmpty()) {
            $bot->sendMessage(
                "😔 По запросу *\"{$this->query}\"* ничего не найдено.\n\nПопробуй другое название.",
                parse_mode: 'Markdown',
            );

            return;
        }

        $browseKey = app(BrowseContext::class)->store($results->pluck('id')->all(), $bot->userId());

        $text = "🔍 Результаты поиска: *\"{$this->query}\"*\n";
        $text .= "Найдено: {$results->total()} рецептов\n\n";

        $keyboard = InlineKeyboardMarkup::make();

        foreach ($results->values() as $pos => $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $vol = $recipe->volume ? " {$recipe->volume}мл" : '';
            $text .= "• {$recipe->name_ru}{$abv}{$vol}\n";

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "🍹 {$recipe->name_ru}",
                    callback_data: "recipe:browse:{$browseKey}:{$pos}",
                ),
            );
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
