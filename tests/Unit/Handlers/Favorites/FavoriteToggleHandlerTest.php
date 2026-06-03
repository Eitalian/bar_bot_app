<?php

use App\Handlers\Favorites\FavoriteToggleHandler;
use App\Models\Favorite;
use App\Models\Recipe;
use App\Models\User;

it('adds to favorites when not favorited', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    (new FavoriteToggleHandler())->handle($user->id, $recipe->id);

    expect(Favorite::where('user_id', $user->id)
        ->where('recipe_id', $recipe->id)
        ->exists())->toBeTrue();
});

it('removes from favorites when already favorited', function () {
    $favorite = Favorite::factory()->create();

    (new FavoriteToggleHandler())->handle($favorite->user_id, $favorite->recipe_id);

    expect(Favorite::where('user_id', $favorite->user_id)
        ->where('recipe_id', $favorite->recipe_id)
        ->exists())->toBeFalse();
});

it('toggling twice returns to original state', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    (new FavoriteToggleHandler())->handle($user->id, $recipe->id);
    expect(Favorite::where('user_id', $user->id)->where('recipe_id', $recipe->id)->exists())->toBeTrue();

    (new FavoriteToggleHandler())->handle($user->id, $recipe->id);
    expect(Favorite::where('user_id', $user->id)->where('recipe_id', $recipe->id)->exists())->toBeFalse();
});
