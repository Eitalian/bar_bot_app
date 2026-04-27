<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

class RemoveInventoryData extends Data
{
    public function __construct(
        public readonly int $user_id,
        public readonly int $inventory_id,
    ) {}
}
