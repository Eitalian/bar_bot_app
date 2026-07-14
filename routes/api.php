<?php

use App\UI\Http\Actions\Favorites\FavoriteToggleAction;
use App\UI\Http\Actions\Favorites\ListFavoritesAction;
use App\UI\Http\Actions\Inventory\AddInventoryAction;
use App\UI\Http\Actions\Inventory\InventoryAction;
use App\UI\Http\Actions\Inventory\RemoveInventoryAction;
use App\UI\Http\Actions\Orders\AcceptOrderAction;
use App\UI\Http\Actions\Orders\CancelOrderAction;
use App\UI\Http\Actions\Orders\ListOrdersAction;
use App\UI\Http\Actions\Orders\PlaceOrderAction;
use App\UI\Http\Actions\Ratings\RateAction;
use App\UI\Http\Actions\Search\GetRecipeAction;
use App\UI\Http\Actions\Search\SearchRecipesAction;
use App\UI\Http\Actions\Session\SessionAction;
use App\UI\Http\Actions\Session\StartSessionAction;
use App\UI\Middleware\CanManageMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('recipes')->group(function () {
    Route::get('/', SearchRecipesAction::class);
    Route::get('/{id}', GetRecipeAction::class);
});

Route::middleware('auth.telegram')->prefix('inventory')->group(function () {
    Route::get('/', InventoryAction::class);

    Route::middleware(CanManageMiddleware::class)->group(function () {
        Route::post('/', AddInventoryAction::class);
        Route::delete('/{id}', [RemoveInventoryAction::class, '__invoke']);
    });
});

Route::middleware('auth.telegram')->group(function () {
    Route::get('/bars/{id}/session', SessionAction::class);

    Route::middleware(CanManageMiddleware::class)->group(function () {
        Route::post('/bars/{id}/session', StartSessionAction::class);
    });

    // Phase 3.1: Orders
    Route::post('/orders', PlaceOrderAction::class);
    Route::get('/sessions/{id}/orders', ListOrdersAction::class);

    Route::middleware(CanManageMiddleware::class)->group(function () {
        Route::patch('/orders/{id}/accept', AcceptOrderAction::class);
        Route::patch('/orders/{id}/cancel', CancelOrderAction::class);
    });

    Route::post('/recipes/{id}/favorite', FavoriteToggleAction::class);
    Route::get('/favorites', ListFavoritesAction::class);

    // BB-11: Ratings (temporary route)
    Route::post('/recipes/{id}/rate', RateAction::class);
});
