<?php

namespace App\Actions\Search;

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchRecipesAction
{
    public function __construct(private SearchRecipesHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = new SearchRecipesData(
            q: $request->string('q')->value() ?: null,
            glass: $request->string('glass')->value() ?: null,
            abvMin: $request->filled('abv_min') ? (float) $request->input('abv_min') : null,
            abvMax: $request->filled('abv_max') ? (float) $request->input('abv_max') : null,
            tag: $request->string('tags')->value() ?: null,
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', 15),
        );

        return response()->json($this->handler->handle($data));
    }
}
