<?php

use App\Handlers\Search\GetRecipeHandler;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeTag;

it('returns recipe with recipeIngredients eager-loaded', function () {
    $recipe = Recipe::factory()->create();
    $ing = Ingredient::factory()->create();
    RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ing->id, 'sort_order' => 1]);

    $result = new GetRecipeHandler()->handle($recipe->id);

    expect($result)->not->toBeNull()
        ->and($result->relationLoaded('recipeIngredients'))->toBeTrue()
        ->and($result->recipeIngredients)->toHaveCount(1);
});

it('returns recipe with tags eager-loaded', function () {
    $recipe = Recipe::factory()->create();
    RecipeTag::create(['recipe_id' => $recipe->id, 'tag' => 'long']);

    $result = new GetRecipeHandler()->handle($recipe->id);

    expect($result->relationLoaded('tags'))->toBeTrue()
        ->and($result->tags)->toHaveCount(1);
});

it('returns null for non-existent id', function () {
    $result = new GetRecipeHandler()->handle('00000000-0000-0000-0000-000000000000');

    expect($result)->toBeNull();
});
