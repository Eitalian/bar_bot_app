<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

class AddInventoryData extends Data
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $ingredient_id,
        public readonly ?float $quantity,
        public readonly ?string $unit,
    ) {}
}
