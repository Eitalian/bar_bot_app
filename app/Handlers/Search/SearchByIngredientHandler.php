<?php

namespace App\Handlers\Search;

use App\Data\Search\SearchByIngredientData;
use App\Data\Search\SearchResult;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

final class SearchByIngredientHandler
{
    public function handle(SearchByIngredientData $data, ?int $userId = null): SearchResult
    {
        if (empty($data->ingredientIds)) {
            return new SearchResult(recipes: collect());
        }

        $ingredientIds = array_unique($data->ingredientIds);
        $count = count($ingredientIds);

        $recipeIds = DB::table('recipe_ingredients')
            ->whereIn('ingredient_id', $ingredientIds)
            ->groupBy('recipe_id')
            ->havingRaw('COUNT(DISTINCT ingredient_id) = ?', [$count])
            ->pluck('recipe_id');

        $recipes = Recipe::whereIn('id', $recipeIds)->orderBy('name_ru')->get();

        if ($userId === null || $recipes->isEmpty()) {
            return new SearchResult(recipes: $recipes);
        }

        $favoritedIds = Favorite::where('user_id', $userId)
            ->whereIn('recipe_id', $recipeIds)
            ->pluck('recipe_id')
            ->flip();

        $avgRatings = Rating::whereIn('recipe_id', $recipeIds)
            ->selectRaw('recipe_id, ROUND(AVG(score), 1) as avg')
            ->groupBy('recipe_id')
            ->pluck('avg', 'recipe_id');

        return new SearchResult(
            recipes: $recipes,
            favoritedIds: $favoritedIds,
            avgRatings: $avgRatings,
        );
    }
}
