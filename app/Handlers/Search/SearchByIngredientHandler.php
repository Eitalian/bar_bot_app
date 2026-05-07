<?php

namespace App\Handlers\Search;

use App\Data\Search\SearchByIngredientData;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SearchByIngredientHandler
{
    /** @return Collection<int, Recipe> */
    public function handle(SearchByIngredientData $data): Collection
    {
        if (empty($data->ingredientIds)) {
            return Recipe::whereRaw('false')->get();
        }

        $count = count($data->ingredientIds);

        $recipeIds = DB::table('recipe_ingredients')
            ->whereIn('ingredient_id', $data->ingredientIds)
            ->groupBy('recipe_id')
            ->havingRaw('COUNT(DISTINCT ingredient_id) = ?', [$count])
            ->pluck('recipe_id');

        return Recipe::whereIn('id', $recipeIds)->orderBy('name_ru')->get();
    }
}
