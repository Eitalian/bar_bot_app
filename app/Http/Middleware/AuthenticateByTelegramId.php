<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateByTelegramId
{
    public function handle(Request $request, Closure $next): Response
    {
        $telegramId = $request->integer('telegram_id');

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        Auth::login($user);

        return $next($request);
    }
}
