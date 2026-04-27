<?php

use App\Data\Inventory\AddInventoryData;
use App\Handlers\Inventory\AddInventoryHandler;
use App\Models\Ingredient;
use App\Models\Inventory;

it('creates a new inventory item', function () {
    $ingredient = Ingredient::factory()->create();

    $data = new AddInventoryData(
        ingredientId: $ingredient->id,
        quantity: 500.0,
        unit: 'мл',
    );

    $item = (new AddInventoryHandler)->handle($data);

    expect($item->ingredient_id)->toBe($ingredient->id)
        ->and($item->quantity)->toBe(500.0)
        ->and($item->unit)->toBe('мл');

    $this->assertDatabaseHas('bar_inventory', [
        'ingredient_id' => $ingredient->id,
        'quantity' => 500.0,
        'unit' => 'мл',
    ]);
});

it('updates existing item for the same ingredient', function () {
    $ingredient = Ingredient::factory()->create();
    Inventory::factory()->create(['ingredient_id' => $ingredient->id, 'quantity' => 200.0]);

    $data = new AddInventoryData(ingredientId: $ingredient->id, quantity: 750.0, unit: 'мл');

    (new AddInventoryHandler)->handle($data);

    expect(Inventory::count())->toBe(1);
    $this->assertDatabaseHas('bar_inventory', ['ingredient_id' => $ingredient->id, 'quantity' => 750.0]);
});

it('saves item without quantity', function () {
    $ingredient = Ingredient::factory()->create();

    $item = (new AddInventoryHandler)->handle(
        new AddInventoryData(ingredientId: $ingredient->id, quantity: null, unit: null),
    );

    expect($item->quantity)->toBeNull()->and($item->unit)->toBeNull();
});
