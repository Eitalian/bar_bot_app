<?php

namespace App\Data\Search;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class SearchResult
{
    public function __construct(
        public LengthAwarePaginator|Collection $recipes,
        public Collection $favoritedIds = new Collection(),
        public Collection $avgRatings = new Collection(),
    ) {}
}
