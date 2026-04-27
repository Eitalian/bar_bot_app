<?php

use App\Handlers\Inventory\ListInventoryHandler;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\User;

it('returns inventory items for the given user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Inventory::factory()->count(3)->create(['user_id' => $user->id]);
    Inventory::factory()->count(2)->create(['user_id' => $other->id]);

    $result = (new ListInventoryHandler)->handle($user->id);

    expect($result)->toHaveCount(3)
        ->each->user_id->toBe($user->id);
});

it('eager loads ingredient relation', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    Inventory::factory()->create(['user_id' => $user->id, 'ingredient_id' => $ingredient->id]);

    $result = (new ListInventoryHandler)->handle($user->id);

    expect($result->first()->relationLoaded('ingredient'))->toBeTrue();
});

it('returns empty collection when user has no inventory', function () {
    $user = User::factory()->create();

    $result = (new ListInventoryHandler)->handle($user->id);

    expect($result)->toBeEmpty();
});
