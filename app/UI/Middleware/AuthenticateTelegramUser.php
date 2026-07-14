<?php

namespace App\UI\Middleware;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;

/**
 * Telegram-транспорт: пользователь auto-create-ится при первом сообщении (гость никогда
 * не 404-ит). См. также App\UI\Middleware\AuthenticateByTelegramId — для HTTP-транспорта
 * политика иная (fail-if-missing), это осознанное расхождение, а не дублирование одной
 * и той же логики.
 */
final class AuthenticateTelegramUser
{
    public function __invoke(Nutgram $bot, callable $next): void
    {
        if ($bot->userId() !== null) {
            $user = User::firstOrCreate(
                ['telegram_id' => $bot->userId()],
                [
                    'first_name' => $bot->user()?->first_name ?? '',
                    'username' => $bot->user()?->username,
                ],
            );
            Auth::setUser($user);
        }

        $next($bot);
    }
}
