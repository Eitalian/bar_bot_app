<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

final class AddInventoryData extends Data
{
    public function __construct(
        public readonly string $ingredientId,
        public readonly ?float $quantity,
        public readonly ?string $unit,
    ) {}
}
