<?php

namespace App\Actions\Search;

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use App\Services\BrowseContext;
use App\Telegram\Responses\SearchResultsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;

final class SearchRecipesAction
{
    public function __construct(
        private SearchRecipesHandler $handler,
        private BrowseContext $browseContext,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = new SearchRecipesData(
            q: $request->string('q')->value() ?: null,
            glass: $request->string('glass')->value() ?: null,
            abvMin: $request->filled('abv_min') ? (float) $request->input('abv_min') : null,
            abvMax: $request->filled('abv_max') ? (float) $request->input('abv_max') : null,
            tag: $request->string('tags')->value() ?: null,
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', config('bar.search.per_page')),
        );

        return response()->json($this->handler->handle($data));
    }

    public function fromTelegram(Nutgram $bot, SearchRecipesData $data): void
    {
        $results = $this->handler->handle($data);

        if ($results->isEmpty()) {
            $bot->sendMessage($this->emptyMessage($data), parse_mode: 'Markdown');

            return;
        }

        $browseKey = $this->browseContext->store($results->pluck('id')->all(), $bot->userId());

        $response = new SearchResultsResponse(
            header: $this->header($data) . "Найдено: {$results->total()} рецептов\n\n",
            recipes: $results->values(),
            browseKey: $browseKey,
        );

        $bot->sendMessage(
            text: $response->text(),
            parse_mode: 'Markdown',
            reply_markup: $response->keyboard(),
        );
    }

    private function header(SearchRecipesData $data): string
    {
        if ($data->q !== null && $data->q !== '') {
            return "🔍 Результаты поиска: *\"{$data->q}\"*\n";
        }

        return "🎛 *Результаты фильтрации:*\n";
    }

    private function emptyMessage(SearchRecipesData $data): string
    {
        if ($data->q !== null && $data->q !== '') {
            return "😔 По запросу *\"{$data->q}\"* ничего не найдено.\n\nПопробуй другое название.";
        }

        return '😔 По выбранным фильтрам ничего не найдено. Попробуйте другие параметры.';
    }
}
