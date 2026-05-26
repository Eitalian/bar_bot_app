<?php

namespace App\Providers;

use App\Data\Inventory\AddInventoryData;
use App\Data\Inventory\RemoveInventoryData;
use App\Data\Orders\AcceptOrderData;
use App\Data\Orders\CancelOrderData;
use App\Data\Orders\PlaceOrderData;
use App\Data\Session\StartSessionData;
use App\Handlers\Inventory\AddInventoryHandler;
use App\Handlers\Inventory\RemoveInventoryHandler;
use App\Handlers\Orders\AcceptOrderHandler;
use App\Handlers\Orders\CancelOrderHandler;
use App\Handlers\Orders\PlaceOrderHandler;
use App\Handlers\Session\StartSessionHandler;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\ServiceProvider;

final class BusServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Dispatcher::class)->map([
            AddInventoryData::class    => AddInventoryHandler::class,
            RemoveInventoryData::class => RemoveInventoryHandler::class,
            StartSessionData::class    => StartSessionHandler::class,
            PlaceOrderData::class      => PlaceOrderHandler::class,
            AcceptOrderData::class     => AcceptOrderHandler::class,
            CancelOrderData::class     => CancelOrderHandler::class,
        ]);
    }
}
