<?php

use App\Models\Favorite;
use App\Models\Recipe;
use App\Models\User;

it('GET /api/favorites returns empty array for new user', function () {
    $user = User::factory()->bartender()->create();

    $this->getJson("/api/favorites?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJson([]);
});

it('GET /api/favorites returns favorited recipes', function () {
    $user = User::factory()->bartender()->create();
    $recipe = Recipe::factory()->create();

    Favorite::factory()->create([
        'user_id' => $user->id,
        'recipe_id' => $recipe->id,
    ]);

    $this->getJson("/api/favorites?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonCount(1);
});
