<?php

namespace App\Handlers\Inventory;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Collection;

class ListInventoryHandler
{
    public function handle(int $userId): Collection
    {
        return Inventory::where('user_id', $userId)
            ->with('ingredient')
            ->orderBy('id')
            ->get();
    }
}
