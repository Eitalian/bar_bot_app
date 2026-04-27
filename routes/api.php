<?php

use App\Actions\Inventory\AddInventoryAction;
use App\Actions\Inventory\InventoryAction;
use App\Actions\Inventory\RemoveInventoryAction;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.telegram')->prefix('inventory')->group(function () {
    Route::get('/', InventoryAction::class);
    Route::post('/', AddInventoryAction::class);
    Route::delete('/{id}', [RemoveInventoryAction::class, '__invoke']);
});
