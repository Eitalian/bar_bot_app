<?php

use App\Models\Recipe;
use App\Models\User;

it('POST /api/recipes/{id}/rate creates rating and returns score', function () {
    $user   = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->postJson("/api/recipes/{$recipe->id}/rate?telegram_id={$user->telegram_id}", ['score' => 4])
        ->assertOk()
        ->assertJson(['score' => 4]);
});

it('POST /api/recipes/{id}/rate upserts on second call', function () {
    $user   = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->postJson("/api/recipes/{$recipe->id}/rate?telegram_id={$user->telegram_id}", ['score' => 4])
        ->assertOk();

    $this->postJson("/api/recipes/{$recipe->id}/rate?telegram_id={$user->telegram_id}", ['score' => 2])
        ->assertOk()
        ->assertJson(['score' => 2]);
});

it('POST /api/recipes/{id}/rate returns 422 for score 0', function () {
    $user   = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->postJson("/api/recipes/{$recipe->id}/rate?telegram_id={$user->telegram_id}", ['score' => 0])
        ->assertUnprocessable();
});

it('POST /api/recipes/{id}/rate returns 422 for score 6', function () {
    $user   = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->postJson("/api/recipes/{$recipe->id}/rate?telegram_id={$user->telegram_id}", ['score' => 6])
        ->assertUnprocessable();
});
