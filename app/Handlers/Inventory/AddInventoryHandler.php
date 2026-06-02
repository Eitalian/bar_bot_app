<?php

namespace App\Handlers\Inventory;

use App\Data\Inventory\AddInventoryData;
use App\Models\Inventory;

final class AddInventoryHandler
{
    public function handle(AddInventoryData $data): Inventory
    {
        return Inventory::query()->updateOrCreate(
            ['bar_id' => 1, 'ingredient_id' => $data->ingredientId],
            ['quantity' => $data->quantity, 'unit' => $data->unit],
        );
    }
}
