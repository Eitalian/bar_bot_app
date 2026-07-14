<?php

namespace App\UI\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP-транспорт: пользователь должен уже существовать (создаётся только через Telegram-старт).
 * См. также App\UI\Middleware\AuthenticateTelegramUser — для Telegram-транспорта политика
 * иная (auto-create), это осознанное расхождение, а не дублирование одной и той же логики.
 */
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
