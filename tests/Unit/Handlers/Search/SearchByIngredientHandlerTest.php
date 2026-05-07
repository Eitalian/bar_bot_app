<?php

use App\Data\Search\SearchByIngredientData;
use App\Handlers\Search\SearchByIngredientHandler;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;

it('returns recipes containing all specified ingredients', function () {
    Ingredient::factory()->create(['id' => 'vodka']);
    Ingredient::factory()->create(['id' => 'lime_juice']);

    $both = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $both->id, 'ingredient_id' => 'vodka', 'sort_order' => 1]);
    RecipeIngredient::create(['recipe_id' => $both->id, 'ingredient_id' => 'lime_juice', 'sort_order' => 2]);

    $onlyVodka = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $onlyVodka->id, 'ingredient_id' => 'vodka', 'sort_order' => 1]);

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: ['vodka', 'lime_juice'])
    );

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($both->id);
});

it('returns all matching recipes for single ingredient', function () {
    Ingredient::factory()->create(['id' => 'rum']);

    $r1 = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $r1->id, 'ingredient_id' => 'rum', 'sort_order' => 1]);

    $r2 = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $r2->id, 'ingredient_id' => 'rum', 'sort_order' => 1]);

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: ['rum'])
    );

    expect($result)->toHaveCount(2);
});

it('returns empty collection when ingredientIds is empty', function () {
    Recipe::factory()->count(3)->create();

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: [])
    );

    expect($result)->toBeEmpty();
});

it('returns empty when no recipe has all ingredients', function () {
    Ingredient::factory()->create(['id' => 'vodka']);
    Ingredient::factory()->create(['id' => 'gin']);

    $recipe = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => 'vodka', 'sort_order' => 1]);

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: ['vodka', 'gin'])
    );

    expect($result)->toBeEmpty();
});

it('orders results by name_ru', function () {
    Ingredient::factory()->create(['id' => 'rum']);

    $b = Recipe::factory()->create(['name_ru' => 'Б рецепт']);
    RecipeIngredient::create(['recipe_id' => $b->id, 'ingredient_id' => 'rum', 'sort_order' => 1]);

    $a = Recipe::factory()->create(['name_ru' => 'А рецепт']);
    RecipeIngredient::create(['recipe_id' => $a->id, 'ingredient_id' => 'rum', 'sort_order' => 1]);

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: ['rum'])
    );

    expect($result->first()->id)->toBe($a->id);
});
