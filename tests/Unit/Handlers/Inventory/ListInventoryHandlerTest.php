<?php

use App\Handlers\Inventory\ListInventoryHandler;
use App\Models\Ingredient;
use App\Models\Inventory;

it('returns all inventory items', function () {
    Inventory::factory()->count(3)->create();

    $result = (new ListInventoryHandler)->handle();

    expect($result)->toHaveCount(3);
});

it('eager loads ingredient relation', function () {
    $ingredient = Ingredient::factory()->create();
    Inventory::factory()->create(['ingredient_id' => $ingredient->id]);

    $result = (new ListInventoryHandler)->handle();

    expect($result->first()->relationLoaded('ingredient'))->toBeTrue();
});

it('returns empty collection when inventory is empty', function () {
    $result = (new ListInventoryHandler)->handle();

    expect($result)->toBeEmpty();
});
