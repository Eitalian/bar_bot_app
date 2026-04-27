<?php

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\User;

it('GET /api/inventory returns all items', function () {
    Inventory::factory()->count(3)->create();

    $user = User::factory()->create();

    $response = $this->getJson("/api/inventory?telegram_id={$user->telegram_id}");

    $response->assertOk()->assertJsonCount(3);
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
    $this->assertDatabaseHas('bar_inventory', ['ingredient_id' => $ingredient->id, 'quantity' => 500]);
});

it('DELETE /api/inventory/{id} removes the item', function () {
    $user = User::factory()->create();
    $item = Inventory::factory()->create();

    $this->deleteJson("/api/inventory/{$item->id}?telegram_id={$user->telegram_id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('bar_inventory', ['id' => $item->id]);
});

it('DELETE /api/inventory/{id} returns 404 for missing item', function () {
    $user = User::factory()->create();

    $this->deleteJson("/api/inventory/999?telegram_id={$user->telegram_id}")
        ->assertNotFound();
});
