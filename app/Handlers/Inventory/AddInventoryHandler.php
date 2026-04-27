<?php

namespace App\Handlers\Inventory;

use App\Data\Inventory\AddInventoryData;
use App\Models\Inventory;

class AddInventoryHandler
{
    public function handle(AddInventoryData $data): Inventory
    {
        return Inventory::updateOrCreate(
            [
                'user_id' => $data->user_id,
                'ingredient_id' => $data->ingredient_id,
            ],
            [
                'quantity' => $data->quantity,
                'unit' => $data->unit,
            ],
        );
    }
}
