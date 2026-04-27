<?php

namespace App\Telegram\Middleware;

use App\Services\TelegramUserResolver;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\UpdateType;

final class AuthenticateTelegramUser
{
    public function __construct(private TelegramUserResolver $resolver) {}

    public function __invoke(Nutgram $bot, callable $next): void
    {
        if ($bot->userId() !== null) {
            $user = $this->resolver->resolve($bot);
            Auth::login($user);
        }

        $next($bot);
    }
}
