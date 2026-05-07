<?php

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use App\Models\Recipe;
use App\Models\RecipeTag;

it('finds recipes by name_ru (case-insensitive)', function () {
    Recipe::factory()->create(['name_ru' => 'Маргарита', 'name_en' => 'Margarita']);
    Recipe::factory()->create(['name_ru' => 'Мохито', 'name_en' => 'Mojito']);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(q: 'марг'));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_ru)->toBe('Маргарита');
});

it('finds recipes by name_en (case-insensitive)', function () {
    Recipe::factory()->create(['name_ru' => 'Маргарита', 'name_en' => 'Margarita']);
    Recipe::factory()->create(['name_ru' => 'Мохито', 'name_en' => 'Mojito']);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(q: 'MOJITO'));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_en)->toBe('Mojito');
});

it('filters by glass type', function () {
    Recipe::factory()->create(['glass' => 'rocks']);
    Recipe::factory()->create(['glass' => 'highball']);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(glass: 'rocks'));

    expect($result->total())->toBe(1);
});

it('filters by abv range', function () {
    Recipe::factory()->create(['abv' => 5.0]);
    Recipe::factory()->create(['abv' => 25.0]);
    Recipe::factory()->create(['abv' => 40.0]);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(abvMin: 10.0, abvMax: 30.0));

    expect($result->total())->toBe(1)
        ->and((float) $result->items()[0]->abv)->toBe(25.0);
});

it('returns non-alcoholic recipes when abvMax is 0', function () {
    Recipe::factory()->create(['abv' => 0.0]);
    Recipe::factory()->create(['abv' => 0.0]);
    Recipe::factory()->create(['abv' => 5.0]);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(abvMin: 0.0, abvMax: 0.0));

    expect($result->total())->toBe(2);
});

it('filters by volume range', function () {
    Recipe::factory()->create(['volume' => 50]);
    Recipe::factory()->create(['volume' => 100]);
    Recipe::factory()->create(['volume' => 300]);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(volMin: 60, volMax: 200));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->volume)->toBe(100);
});

it('filters by tag', function () {
    $tagged = Recipe::factory()->create();
    RecipeTag::create(['recipe_id' => $tagged->id, 'tag' => 'long']);
    Recipe::factory()->create();

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(tag: 'long'));

    expect($result->total())->toBe(1);
});

it('returns empty paginator when no match', function () {
    Recipe::factory()->count(3)->create();

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(q: 'невозможноеназвание'));

    expect($result->isEmpty())->toBeTrue();
});

it('returns all recipes when no filters applied', function () {
    Recipe::factory()->count(5)->create();

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData);

    expect($result->total())->toBe(5);
});

it('paginates results correctly', function () {
    Recipe::factory()->count(10)->create();

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(page: 2, perPage: 3));

    expect($result->currentPage())->toBe(2)
        ->and(count($result->items()))->toBe(3);
});
