<?php

namespace App\Data\Search;

use Spatie\LaravelData\Data;

final class SearchRecipesData extends Data
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $glass = null,
        public readonly ?float $abvMin = null,
        public readonly ?float $abvMax = null,
        public readonly ?int $volMin = null,
        public readonly ?int $volMax = null,
        public readonly ?string $tag = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}
}
