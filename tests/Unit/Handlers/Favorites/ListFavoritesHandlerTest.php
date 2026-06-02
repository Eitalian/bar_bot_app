<?php

use App\Handlers\Favorites\ListFavoritesHandler;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\Recipe;
use App\Models\User;

it('returns empty collection for user with no favorites', function () {
    $user = User::factory()->create();

    $result = (new ListFavoritesHandler())->handle($user->id);

    expect($result)->toBeEmpty();
});

it('returns favorites ordered by score desc then name asc', function () {
    $user = User::factory()->create();

    $recipeA = Recipe::factory()->create(['name_ru' => 'Апероль Шприц']);
    $recipeB = Recipe::factory()->create(['name_ru' => 'Беллини']);
    $recipeC = Recipe::factory()->create(['name_ru' => 'Виски Сауэр']);

    Favorite::factory()->create(['user_id' => $user->id, 'recipe_id' => $recipeA->id]);
    Favorite::factory()->create(['user_id' => $user->id, 'recipe_id' => $recipeB->id]);
    Favorite::factory()->create(['user_id' => $user->id, 'recipe_id' => $recipeC->id]);

    Rating::factory()->create(['user_id' => $user->id, 'recipe_id' => $recipeA->id, 'score' => 3]);
    Rating::factory()->create(['user_id' => $user->id, 'recipe_id' => $recipeC->id, 'score' => 5]);
    // recipeB has no rating

    $result = (new ListFavoritesHandler())->handle($user->id);

    expect($result)->toHaveCount(3);
    // Score 5 first, then score 3, then null (no rating) — alphabetical among ties
    expect($result[0]->id)->toBe($recipeC->id); // score 5
    expect($result[1]->id)->toBe($recipeA->id); // score 3
    expect($result[2]->id)->toBe($recipeB->id); // null score, last
});

it('includes user_score attribute on rated recipes', function () {
    $user = User::factory()->create();

    $ratedRecipe = Recipe::factory()->create();
    $unratedRecipe = Recipe::factory()->create();

    Favorite::factory()->create(['user_id' => $user->id, 'recipe_id' => $ratedRecipe->id]);
    Favorite::factory()->create(['user_id' => $user->id, 'recipe_id' => $unratedRecipe->id]);

    Rating::factory()->create(['user_id' => $user->id, 'recipe_id' => $ratedRecipe->id, 'score' => 4]);

    $result = (new ListFavoritesHandler())->handle($user->id);

    $ratedResult = $result->firstWhere('id', $ratedRecipe->id);
    $unratedResult = $result->firstWhere('id', $unratedRecipe->id);

    expect($ratedResult->user_score)->toBe(4);
    expect($unratedResult->user_score)->toBeNull();
});
