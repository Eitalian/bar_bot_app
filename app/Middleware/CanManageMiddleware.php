<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use SergiX44\Nutgram\Nutgram;

final class CanManageMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        Gate::authorize('can-manage');

        return $next($request);
    }

    public function __invoke(Nutgram $bot, callable $next): void
    {
        Gate::authorize('can-manage');

        $next($bot);
    }
}
