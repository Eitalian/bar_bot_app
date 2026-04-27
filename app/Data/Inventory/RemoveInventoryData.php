<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

final class RemoveInventoryData extends Data
{
    public function __construct(
        public readonly int $inventoryId,
    ) {}
}
