<?php

namespace App\Handlers\Inventory;

use App\Data\Inventory\RemoveInventoryData;
use App\Models\Inventory;

final class RemoveInventoryHandler
{
    public function handle(RemoveInventoryData $data): bool
    {
        return (bool) Inventory::where('id', $data->inventoryId)->delete();
    }
}
