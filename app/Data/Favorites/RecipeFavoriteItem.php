<?php

namespace App\Data\Favorites;

final readonly class RecipeFavoriteItem
{
    public function __construct(
        public string $id,
        public string $name_ru,
        public ?float $abv,
        public ?int $volume,
        public ?int $userScore,
        public ?float $avgRating,
    ) {}
}
