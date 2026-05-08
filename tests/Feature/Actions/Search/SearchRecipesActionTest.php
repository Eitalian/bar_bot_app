<?php

use App\Models\Recipe;
use App\Models\RecipeTag;

it('GET /api/recipes returns all recipes without filters', function () {
    Recipe::factory()->count(3)->create();

    $this->getJson('/api/recipes')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('GET /api/recipes?q= filters by name', function () {
    Recipe::factory()->create(['name_ru' => 'Маргарита', 'name_en' => 'Margarita']);
    Recipe::factory()->create(['name_ru' => 'Мохито', 'name_en' => 'Mojito']);

    $this->getJson('/api/recipes?q=марг')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name_ru' => 'Маргарита']);
});

it('GET /api/recipes?glass= filters by glass type', function () {
    Recipe::factory()->create(['glass' => 'rocks']);
    Recipe::factory()->create(['glass' => 'highball']);
    Recipe::factory()->create(['glass' => 'highball']);

    $this->getJson('/api/recipes?glass=highball')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/recipes?abv_min=&abv_max= filters by ABV range', function () {
    Recipe::factory()->create(['abv' => 5.0]);
    Recipe::factory()->create(['abv' => 25.0]);
    Recipe::factory()->create(['abv' => 40.0]);

    $this->getJson('/api/recipes?abv_min=10&abv_max=30')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('GET /api/recipes?tags= filters by tag', function () {
    $tagged = Recipe::factory()->create();
    RecipeTag::create(['recipe_id' => $tagged->id, 'tag' => 'long']);
    Recipe::factory()->create();

    $this->getJson('/api/recipes?tags=long')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('GET /api/recipes returns paginated response structure', function () {
    Recipe::factory()->count(5)->create();

    $this->getJson('/api/recipes?per_page=2')
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'last_page', 'total', 'per_page']);
});
