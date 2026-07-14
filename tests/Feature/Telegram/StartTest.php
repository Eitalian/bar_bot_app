<?php

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

it('/start отвечает главным меню', function () {
    $telegramId = 111000001;
    User::factory()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearText('/start')
        ->reply()
        ->assertCalled('sendMessage');
});
