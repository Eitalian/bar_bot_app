<?php

use App\Handlers\Ratings\RateRecipeHandler;
use App\Models\Rating;
use App\Models\Recipe;
use App\Models\User;

it('creates a new rating', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $rating = (new RateRecipeHandler())->handle($user->id, $recipe->id, 5);

    expect($rating->user_id)->toBe($user->id)
        ->and($rating->recipe_id)->toBe($recipe->id)
        ->and($rating->score)->toBe(5);

    $this->assertDatabaseHas('ratings', [
        'user_id' => $user->id,
        'recipe_id' => $recipe->id,
        'score' => 5,
    ]);
});

it('updates existing rating on second call', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    (new RateRecipeHandler())->handle($user->id, $recipe->id, 3);
    $rating = (new RateRecipeHandler())->handle($user->id, $recipe->id, 5);

    expect(Rating::count())->toBe(1)
        ->and($rating->score)->toBe(5);

    $this->assertDatabaseHas('ratings', [
        'user_id' => $user->id,
        'recipe_id' => $recipe->id,
        'score' => 5,
    ]);
});

it('throws InvalidArgumentException on score 0', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    (new RateRecipeHandler())->handle($user->id, $recipe->id, 0);
})->throws(\InvalidArgumentException::class, 'Score must be between 1 and 5');

it('throws InvalidArgumentException on score 6', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    (new RateRecipeHandler())->handle($user->id, $recipe->id, 6);
})->throws(\InvalidArgumentException::class, 'Score must be between 1 and 5');
