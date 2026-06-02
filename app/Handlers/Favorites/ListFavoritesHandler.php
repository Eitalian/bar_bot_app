<?php

namespace App\Handlers\Favorites;

use App\Models\Recipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListFavoritesHandler
{
    /**
     * Return a collection of Recipe models (with user_score attribute) for the given user's favorites.
     *
     * Ordered by: user_score DESC NULLS LAST, name_ru ASC.
     *
     * @return Collection<int, Recipe>
     */
    public function handle(int $userId): Collection
    {
        $rows = DB::table('favorites')
            ->join('recipes', 'recipes.id', '=', 'favorites.recipe_id')
            ->leftJoin('ratings', function ($join) use ($userId) {
                $join->on('ratings.recipe_id', '=', 'favorites.recipe_id')
                    ->where('ratings.user_id', '=', $userId);
            })
            ->where('favorites.user_id', $userId)
            ->orderByRaw('ratings.score DESC NULLS LAST')
            ->orderBy('recipes.name_ru', 'asc')
            ->select('recipes.*', 'ratings.score as user_score')
            ->get();

        return $rows->map(function (object $row) {
            $recipe = new Recipe();
            $recipe->setRawAttributes((array) $row, true);
            $recipe->user_score = isset($row->user_score) ? (int) $row->user_score : null;

            return $recipe;
        });
    }
}
