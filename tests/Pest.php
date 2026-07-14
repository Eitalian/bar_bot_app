<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Telegram\Types\User\User as TelegramUser;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/** Фейковый Telegram-пользователь для смоук-тестов бота (tests/Feature/Telegram). */
function tgUser(int $id, string $firstName = 'Guest'): TelegramUser
{
    return TelegramUser::fromArray(['id' => $id, 'is_bot' => false, 'first_name' => $firstName]);
}
