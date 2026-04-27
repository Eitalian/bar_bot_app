<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;

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
            Auth::login($user);
        }

        $next($bot);
    }
}
