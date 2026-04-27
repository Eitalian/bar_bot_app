<?php

namespace App\Services;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class TelegramUserResolver
{
    public function resolve(Nutgram $bot): User
    {
        return User::firstOrCreate(
            ['telegram_id' => $bot->userId()],
            [
                'first_name' => $bot->user()?->first_name ?? '',
                'username' => $bot->user()?->username,
            ],
        );
    }
}
