<?php

namespace App\Handlers\Ratings;

use App\Models\Rating;

final class RateRecipeHandler
{
    public function handle(int $userId, string $recipeId, int $score): Rating
    {
        if ($score < 1 || $score > 5) {
            throw new \InvalidArgumentException('Score must be between 1 and 5');
        }

        return Rating::query()->updateOrCreate(
            ['user_id' => $userId, 'recipe_id' => $recipeId],
            ['score' => $score],
        )->fresh();
    }
}
