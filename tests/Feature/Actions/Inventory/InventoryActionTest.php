<?php

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\User;

it('GET /api/inventory returns items for the given user', function () {
    $user = User::factory()->create();
    Inventory::factory()->count(2)->create(['user_id' => $user->id]);
    Inventory::factory()->create(); // other user

    $response = $this->getJson("/api/inventory?telegram_id={$user->telegram_id}");

    $response->assertOk()->assertJsonCount(2);
});

it('GET /api/inventory returns 404 for unknown telegram_id', function () {
    $this->getJson('/api/inventory?telegram_id=999999999')->assertNotFound();
});

it('POST /api/inventory creates an inventory item', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();

    $response = $this->postJson('/api/inventory', [
        'telegram_id' => $user->telegram_id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 500,
        'unit' => 'мл',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('bar_inventory', [
        'user_id' => $user->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 500,
    ]);
});

it('DELETE /api/inventory/{id} removes the item', function () {
    $user = User::factory()->create();
    $item = Inventory::factory()->create(['user_id' => $user->id]);

    $this->deleteJson("/api/inventory/{$item->id}?telegram_id={$user->telegram_id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('bar_inventory', ['id' => $item->id]);
});

it('DELETE /api/inventory/{id} returns 404 for wrong user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = Inventory::factory()->create(['user_id' => $owner->id]);

    $this->deleteJson("/api/inventory/{$item->id}?telegram_id={$other->telegram_id}")
        ->assertNotFound();
});
