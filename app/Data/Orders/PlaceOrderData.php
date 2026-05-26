<?php

namespace App\Data\Orders;

use Spatie\LaravelData\Data;

final class PlaceOrderData extends Data
{
    public function __construct(
        public readonly string $recipeId,
        public readonly int $userId,
    ) {}
}
