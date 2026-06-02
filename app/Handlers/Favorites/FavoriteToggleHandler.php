<?php

namespace App\Handlers\Favorites;

use App\Models\Favorite;

final class FavoriteToggleHandler
{
    public function handle(int $userId, string $recipeId): bool
    {
        $favorite = Favorite::where('user_id', $userId)
            ->where('recipe_id', $recipeId)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return false;
        }

        Favorite::create([
            'user_id'   => $userId,
            'recipe_id' => $recipeId,
        ]);

        return true;
    }
}
