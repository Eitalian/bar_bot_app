<?php

namespace App\Handlers\Search;

use App\Models\Recipe;

final class GetRecipeHandler
{
    public function handle(string $id): ?Recipe
    {
        return Recipe::with('recipeIngredients', 'tags')->find($id);
    }
}
