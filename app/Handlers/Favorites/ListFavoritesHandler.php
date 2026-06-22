<?php

namespace App\Handlers\Favorites;

use App\Data\Favorites\FavoritesPage;
use App\Data\Favorites\RecipeFavoriteItem;
use Illuminate\Support\Facades\DB;

final class ListFavoritesHandler
{
    public function handle(int $userId, int $page = 1, int $perPage = 10): FavoritesPage
    {
        $total = DB::table('favorites')->where('user_id', $userId)->count();

        $rows = DB::table('favorites')
            ->join('recipes', 'recipes.id', '=', 'favorites.recipe_id')
            ->leftJoin('ratings as ur', function ($join) use ($userId) {
                $join->on('ur.recipe_id', '=', 'favorites.recipe_id')
                    ->where('ur.user_id', '=', $userId);
            })
            ->leftJoin(
                DB::raw('(SELECT recipe_id, ROUND(AVG(score)::numeric, 1) as avg_score FROM ratings GROUP BY recipe_id) as avg_r'),
                'avg_r.recipe_id',
                '=',
                'favorites.recipe_id',
            )
            ->where('favorites.user_id', $userId)
            ->orderByRaw('ur.score DESC NULLS LAST')
            ->orderBy('recipes.name_ru', 'asc')
            ->select(
                'recipes.id',
                'recipes.name_ru',
                'recipes.abv',
                'recipes.volume',
                'ur.score as user_score',
                'avg_r.avg_score as avg_rating',
            )
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn(object $row) => new RecipeFavoriteItem(
            id: $row->id,
            name_ru: $row->name_ru,
            abv: $row->abv !== null ? (float) $row->abv : null,
            volume: $row->volume !== null ? (int) $row->volume : null,
            userScore: $row->user_score !== null ? (int) $row->user_score : null,
            avgRating: $row->avg_rating !== null ? (float) $row->avg_rating : null,
        ));

        return new FavoritesPage(
            items: $items,
            total: $total,
            perPage: $perPage,
            page: $page,
        );
    }
}
