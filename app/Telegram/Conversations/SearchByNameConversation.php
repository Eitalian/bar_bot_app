<?php

namespace App\Telegram\Conversations;

use App\Actions\Search\SearchRecipesAction;
use App\Data\Search\SearchRecipesData;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

final class SearchByNameConversation extends Conversation
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

        app(SearchRecipesAction::class)->fromTelegram($bot, $data);
    }
}
