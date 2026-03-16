<?php

namespace App\Telegram\Conversations;

use App\Models\Recipe;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SearchByNameConversation extends Conversation
{
    protected const PER_PAGE = 5;

    protected ?string $query = null;

    protected int $page = 1;

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

        $this->page = 1;
        $this->showResults($bot);
        $this->end();
    }

    private function showResults(Nutgram $bot): void
    {
        $query = $this->query;
        $page = $this->page;

        $results = Recipe::where('name_ru', 'ilike', "%{$query}%")
            ->orWhere('name_en', 'ilike', "%{$query}%")
            ->orderBy('name_ru')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        if ($results->isEmpty()) {
            $bot->sendMessage("😔 По запросу *\"{$query}\"* ничего не найдено.\n\nПопробуй другое название.", parse_mode: 'Markdown');

            return;
        }

        $text = "🔍 Результаты поиска: *\"{$query}\"*\n";
        $text .= "Найдено: {$results->total()} | Страница {$page}/{$results->lastPage()}\n\n";

        $keyboard = InlineKeyboardMarkup::make();

        foreach ($results as $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $vol = $recipe->volume ? " {$recipe->volume}мл" : '';
            $text .= "• {$recipe->name_ru}{$abv}{$vol}\n";

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "🍹 {$recipe->name_ru}",
                    callback_data: "recipe:show:{$recipe->id}",
                ),
            );
        }

        // Навигация
        $nav = [];
        if ($results->currentPage() > 1) {
            $nav[] = InlineKeyboardButton::make('◀️', callback_data: 'search:page:' . ($page - 1) . ":{$query}");
        }
        if ($results->hasMorePages()) {
            $nav[] = InlineKeyboardButton::make('▶️', callback_data: 'search:page:' . ($page + 1) . ":{$query}");
        }
        if (! empty($nav)) {
            $keyboard->addRow(...$nav);
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
