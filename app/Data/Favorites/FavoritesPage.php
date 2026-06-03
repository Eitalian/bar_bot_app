<?php

namespace App\Data\Favorites;

use Illuminate\Support\Collection;

final readonly class FavoritesPage
{
    /** @param Collection<int, RecipeFavoriteItem> $items */
    public function __construct(
        public Collection $items,
        public int $total,
        public int $perPage,
        public int $page,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
