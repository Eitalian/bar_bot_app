<?php

use App\Data\Inventory\AddInventoryData;
use App\Handlers\Inventory\AddInventoryHandler;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\User;

it('creates a new inventory item', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();

    $data = new AddInventoryData(
        user_id: $user->id,
        ingredient_id: $ingredient->id,
        quantity: 500.0,
        unit: 'мл',
    );

    $item = (new AddInventoryHandler)->handle($data);

    expect($item->user_id)->toBe($user->id)
        ->and($item->ingredient_id)->toBe($ingredient->id)
        ->and($item->quantity)->toBe(500.0)
        ->and($item->unit)->toBe('мл');

    $this->assertDatabaseHas('bar_inventory', [
        'user_id' => $user->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 500.0,
        'unit' => 'мл',
    ]);
});

it('updates existing inventory item for the same user and ingredient', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    Inventory::factory()->create([
        'user_id' => $user->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 200.0,
        'unit' => 'мл',
    ]);

    $data = new AddInventoryData(
        user_id: $user->id,
        ingredient_id: $ingredient->id,
        quantity: 750.0,
        unit: 'мл',
    );

    (new AddInventoryHandler)->handle($data);

    expect(Inventory::where('user_id', $user->id)->count())->toBe(1);
    $this->assertDatabaseHas('bar_inventory', ['user_id' => $user->id, 'quantity' => 750.0]);
});

it('saves item without quantity', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();

    $data = new AddInventoryData(
        user_id: $user->id,
        ingredient_id: $ingredient->id,
        quantity: null,
        unit: null,
    );

    $item = (new AddInventoryHandler)->handle($data);

    expect($item->quantity)->toBeNull()
        ->and($item->unit)->toBeNull();
});
