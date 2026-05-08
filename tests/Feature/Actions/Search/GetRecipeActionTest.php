<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeTag;

it('GET /api/recipes/{id} returns recipe with ingredients and tags', function () {
    $recipe = Recipe::factory()->create(['name_ru' => 'Маргарита', 'name_en' => 'Margarita']);
    $ing = Ingredient::factory()->create();
    RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ing->id, 'sort_order' => 1]);
    RecipeTag::create(['recipe_id' => $recipe->id, 'tag' => 'long']);

    $this->getJson("/api/recipes/{$recipe->id}")
        ->assertOk()
        ->assertJsonFragment(['name_ru' => 'Маргарита'])
        ->assertJsonStructure(['id', 'name_ru', 'name_en', 'abv', 'volume', 'glass', 'recipe_ingredients', 'tags']);
});

it('GET /api/recipes/{id} returns 404 for unknown id', function () {
    $this->getJson('/api/recipes/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});
