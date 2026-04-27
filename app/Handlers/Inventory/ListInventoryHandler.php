<?php

namespace App\Handlers\Inventory;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Collection;

final class ListInventoryHandler
{
    /** @return Collection<int, Inventory> */
    public function handle(): Collection
    {
        return Inventory::with('ingredient')->orderBy('id')->get();
    }
}
