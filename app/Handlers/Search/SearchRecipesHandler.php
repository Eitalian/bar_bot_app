<?php

namespace App\Handlers\Search;

use App\Data\Search\SearchRecipesData;
use App\Data\Search\SearchResult;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\Recipe;

final class SearchRecipesHandler
{
    public function handle(SearchRecipesData $data, ?int $userId = null): SearchResult
    {
        $query = Recipe::query()->orderBy('name_ru');

        if ($data->q !== null && $data->q !== '') {
            $query->where(function ($q) use ($data): void {
                $q->where('name_ru', 'ilike', "%{$data->q}%")
                    ->orWhere('name_en', 'ilike', "%{$data->q}%");
            });
        }

        if ($data->glass !== null && $data->glass !== '') {
            $query->where('glass', $data->glass);
        }

        if ($data->abvMin !== null && $data->abvMax !== null) {
            $query->whereBetween('abv', [$data->abvMin, $data->abvMax]);
        }

        if ($data->volMin !== null && $data->volMax !== null) {
            $query->whereBetween('volume', [$data->volMin, $data->volMax]);
        }

        if ($data->tag !== null && $data->tag !== '') {
            $query->whereHas('tags', fn($q) => $q->where('tag', $data->tag));
        }

        $paginator = $query->paginate($data->perPage, ['*'], 'page', $data->page);

        if ($userId === null || $paginator->isEmpty()) {
            return new SearchResult(recipes: $paginator);
        }

        $recipeIds = $paginator->pluck('id');

        $favoritedIds = Favorite::where('user_id', $userId)
            ->whereIn('recipe_id', $recipeIds)
            ->pluck('recipe_id')
            ->flip();

        $avgRatings = Rating::whereIn('recipe_id', $recipeIds)
            ->selectRaw('recipe_id, ROUND(AVG(score), 1) as avg')
            ->groupBy('recipe_id')
            ->pluck('avg', 'recipe_id');

        return new SearchResult(
            recipes: $paginator,
            favoritedIds: $favoritedIds,
            avgRatings: $avgRatings,
        );
    }
}
