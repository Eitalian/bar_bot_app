<?php

namespace App\Handlers\Search;

use App\Data\Search\GetRecipeResult;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\Recipe;

final class GetRecipeHandler
{
    public function handle(string $id, ?int $userId = null): GetRecipeResult
    {
        $recipe = Recipe::with('recipeIngredients', 'tags')->find($id);

        if ($recipe === null) {
            return new GetRecipeResult(recipe: null);
        }

        $isFavorite = false;
        $userRating = null;
        $avgRating = null;
        $ratingsCount = 0;

        if ($userId !== null) {
            $isFavorite = Favorite::where('user_id', $userId)->where('recipe_id', $id)->exists();
            $userRating = Rating::where('user_id', $userId)->where('recipe_id', $id)->value('score');
        }

        $stats = Rating::where('recipe_id', $id)
            ->selectRaw('ROUND(AVG(score), 1) as avg, COUNT(*) as cnt')
            ->first();

        if ($stats && $stats->cnt > 0) {
            $avgRating = (float) $stats->avg;
            $ratingsCount = (int) $stats->cnt;
        }

        return new GetRecipeResult(
            recipe: $recipe,
            isFavorite: $isFavorite,
            userRating: $userRating !== null ? (int) $userRating : null,
            avgRating: $avgRating,
            ratingsCount: $ratingsCount,
        );
    }
}
