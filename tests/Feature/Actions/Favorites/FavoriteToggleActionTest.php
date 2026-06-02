<?php

use App\Models\Recipe;
use App\Models\User;

it('POST /api/recipes/{id}/favorite returns favorited true when adding', function () {
    $user = User::factory()->bartender()->create();
    $recipe = Recipe::factory()->create();

    $this->postJson("/api/recipes/{$recipe->id}/favorite?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJson(['favorited' => true]);
});

it('POST /api/recipes/{id}/favorite returns favorited false when removing', function () {
    $user = User::factory()->bartender()->create();
    $recipe = Recipe::factory()->create();

    // Toggle once to add
    $this->postJson("/api/recipes/{$recipe->id}/favorite?telegram_id={$user->telegram_id}");

    // Toggle again to remove
    $this->postJson("/api/recipes/{$recipe->id}/favorite?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJson(['favorited' => false]);
});

it('POST /api/recipes/{id}/favorite returns 404 for unknown telegram_id', function () {
    $recipe = Recipe::factory()->create();

    $this->postJson("/api/recipes/{$recipe->id}/favorite?telegram_id=999999999")
        ->assertNotFound();
});
