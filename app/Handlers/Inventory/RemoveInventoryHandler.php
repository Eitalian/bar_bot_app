<?php

namespace App\Handlers\Inventory;

use App\Data\Inventory\RemoveInventoryData;
use App\Models\Inventory;

class RemoveInventoryHandler
{
    public function handle(RemoveInventoryData $data): bool
    {
        return (bool) Inventory::where('id', $data->inventory_id)
            ->where('user_id', $data->user_id)
            ->delete();
    }
}
