<?php

use App\Models\Rating;
use App\Models\Recipe;
use App\Models\User;

it('full flow: favorite → rate → upsert → unfavorite, rating persists', function () {
    $user   = User::factory()->create();
    $recipe = Recipe::factory()->create();

    // 1. GET /api/favorites → пустой массив
    $this->getJson("/api/favorites?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertExactJson([]);

    // 2. POST favorite → {favorited: true}
    $this->postJson("/api/recipes/{$recipe->id}/favorite?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJson(['favorited' => true]);

    // 3. GET /api/favorites → массив с рецептом
    $this->getJson("/api/favorites?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonCount(1);

    // 4. POST rate score=4 → {score:4, avg:4.0, count:1}
    $this->postJson("/api/recipes/{$recipe->id}/rate?telegram_id={$user->telegram_id}", ['score' => 4])
        ->assertOk()
        ->assertJson(['score' => 4, 'count' => 1]);

    // 5. POST rate score=2 (upsert) → {score:2, avg:2.0, count:1}
    $this->postJson("/api/recipes/{$recipe->id}/rate?telegram_id={$user->telegram_id}", ['score' => 2])
        ->assertOk()
        ->assertJson(['score' => 2, 'count' => 1]);

    // 6. POST favorite второй раз → {favorited: false}
    $this->postJson("/api/recipes/{$recipe->id}/favorite?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJson(['favorited' => false]);

    // 7. GET /api/favorites → пустой массив (favorite удалён)
    $this->getJson("/api/favorites?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertExactJson([]);

    // 8. Проверить что оценка в ratings осталась
    expect(Rating::where('recipe_id', $recipe->id)->count())->toBe(1);
    expect(Rating::where('recipe_id', $recipe->id)->value('score'))->toBe(2);
});

it('GET /api/recipes/{id} returns favorites and ratings context', function () {
    $user   = User::factory()->create();
    $recipe = Recipe::factory()->create();

    // Без избранного и оценки
    $this->getJson("/api/recipes/{$recipe->id}?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJson([
            'is_favorite'   => false,
            'user_rating'   => null,
            'ratings_count' => 0,
        ]);

    // Добавить в избранное и выставить оценку
    $this->postJson("/api/recipes/{$recipe->id}/favorite?telegram_id={$user->telegram_id}");
    $this->postJson("/api/recipes/{$recipe->id}/rate?telegram_id={$user->telegram_id}", ['score' => 5]);

    $this->getJson("/api/recipes/{$recipe->id}?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJson([
            'is_favorite'   => true,
            'user_rating'   => 5,
            'ratings_count' => 1,
        ]);
});
