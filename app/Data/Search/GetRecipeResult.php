<?php

namespace App\Data\Search;

use App\Models\Recipe;

final readonly class GetRecipeResult
{
    public function __construct(
        public ?Recipe $recipe,
        public bool $isFavorite = false,
        public ?int $userRating = null,
        public ?float $avgRating = null,
        public int $ratingsCount = 0,
    ) {}
}
