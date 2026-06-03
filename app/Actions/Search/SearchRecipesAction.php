<?php

namespace App\Actions\Search;

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use App\Services\BrowseContext;
use App\Telegram\Responses\SearchResultsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return response()->json($this->handler->handle($data)->recipes);
    }

    public function fromTelegram(Nutgram $bot, SearchRecipesData $data): void
    {
        $result = $this->handler->handle($data, Auth::id());

        if ($result->recipes->isEmpty()) {
            $bot->sendMessage($this->emptyMessage($data), parse_mode: 'Markdown');

            return;
        }

        $browseKey = $this->browseContext->store($result->recipes->pluck('id')->all(), $bot->userId());

        $response = new SearchResultsResponse(
            header: $this->header($data) . "Найдено: {$result->recipes->total()} рецептов\n\n",
            recipes: $result->recipes->values(),
            browseKey: $browseKey,
            favoritedIds: $result->favoritedIds,
            avgRatings: $result->avgRatings,
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
