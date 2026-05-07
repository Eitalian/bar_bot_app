<?php

namespace App\Handlers\Search;

use App\Data\Search\SearchRecipesData;
use App\Models\Recipe;
use Illuminate\Pagination\LengthAwarePaginator;

final class SearchRecipesHandler
{
    public function handle(SearchRecipesData $data): LengthAwarePaginator
    {
        $query = Recipe::query()->orderBy('name_ru');

        if ($data->q !== null && $data->q !== '') {
            $query->where(function ($q) use ($data): void {
                $q->where('name_ru', 'ilike', "%{$data->q}%")
                    ->orWhere('name_en', 'ilike', "%{$data->q}%");
            });
        }

        if ($data->glass !== null) {
            $query->where('glass', $data->glass);
        }

        if ($data->abvMin !== null && $data->abvMax === 0.0) {
            $query->where('abv', 0);
        } elseif ($data->abvMin !== null && $data->abvMax !== null) {
            $query->whereBetween('abv', [$data->abvMin, $data->abvMax]);
        }

        if ($data->volMin !== null && $data->volMax !== null) {
            $query->whereBetween('volume', [$data->volMin, $data->volMax]);
        }

        if ($data->tag !== null) {
            $query->whereHas('tags', fn ($q) => $q->where('tag', $data->tag));
        }

        return $query->paginate($data->perPage, ['*'], 'page', $data->page);
    }
}
