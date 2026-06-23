<?php

namespace App\Providers;

use App\Models\Bar;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Bar::class, fn() => Bar::default());
    }

    public function boot(): void
    {
        //
    }
}
