<?php

use App\Actions\Inventory\AddInventoryAction;
use App\Actions\Inventory\InventoryAction;
use App\Actions\Inventory\RemoveInventoryAction;
use App\Middleware\CanManageMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.telegram')->prefix('inventory')->group(function () {
    Route::get('/', InventoryAction::class);

    Route::middleware(CanManageMiddleware::class)->group(function () {
        Route::post('/', AddInventoryAction::class);
        Route::delete('/{id}', [RemoveInventoryAction::class, '__invoke']);
    });
});
