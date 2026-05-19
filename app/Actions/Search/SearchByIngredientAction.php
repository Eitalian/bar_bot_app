<?php

namespace App\Actions\Search;

use App\Data\Search\SearchByIngredientData;
use App\Handlers\Search\SearchByIngredientHandler;
use App\Services\BrowseContext;
use App\Telegram\Responses\SearchResultsResponse;
use SergiX44\Nutgram\Nutgram;

final class SearchByIngredientAction
{
    private const RESULTS_LIMIT = 10;

    public function __construct(
        private SearchByIngredientHandler $handler,
        private BrowseContext $browseContext,
    ) {}

    public function fromTelegram(Nutgram $bot, SearchByIngredientData $data): void
    {
        $list = implode(', ', $data->ingredientIds);
        $recipes = $this->handler->handle($data);

        if ($recipes->isEmpty()) {
            $bot->sendMessage(
                "😔 Нет коктейлей со *всеми* ингредиентами: `{$list}`\n\nПопробуйте убрать один из ингредиентов.",
                parse_mode: 'Markdown',
            );

            return;
        }

        $browseKey = $this->browseContext->store($recipes->pluck('id')->all(), $bot->userId());

        $total = $recipes->count();
        $shown = $recipes->take(self::RESULTS_LIMIT)->values();
        $overflow = $total > self::RESULTS_LIMIT
            ? '_...и ещё ' . ($total - self::RESULTS_LIMIT) . ' рецептов_'
            : null;

        $response = new SearchResultsResponse(
            header: "🧪 Ингредиенты: `{$list}`\nНайдено коктейлей: *{$total}*\n\n",
            recipes: $shown,
            browseKey: $browseKey,
            showVolume: false,
            overflowText: $overflow,
        );

        $bot->sendMessage(
            text: $response->text(),
            parse_mode: 'Markdown',
            reply_markup: $response->keyboard(),
        );
    }
}
