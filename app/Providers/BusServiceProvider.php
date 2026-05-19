<?php

namespace App\Providers;

use App\Data\Inventory\AddInventoryData;
use App\Data\Inventory\RemoveInventoryData;
use App\Data\Session\StartSessionData;
use App\Handlers\Inventory\AddInventoryHandler;
use App\Handlers\Inventory\RemoveInventoryHandler;
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
        ]);
    }
}
