<?php

namespace App\Data\Search;

use Spatie\LaravelData\Data;

final class SearchByIngredientData extends Data
{
    public function __construct(
        /** @var string[] */
        public readonly array $ingredientIds,
    ) {}
}
