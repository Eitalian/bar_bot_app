<?php

namespace App\Handlers\Inventory;

use App\Data\Inventory\AddInventoryData;
use App\Models\Inventory;

final class AddInventoryHandler
{
    public function handle(AddInventoryData $data): Inventory
    {
        return Inventory::updateOrCreate(
            ['ingredient_id' => $data->ingredientId],
            ['quantity' => $data->quantity, 'unit' => $data->unit],
        );
    }
}
