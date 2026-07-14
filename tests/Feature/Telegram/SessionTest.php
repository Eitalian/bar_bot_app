<?php

use App\Models\BarSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use SergiX44\Nutgram\Nutgram;

afterEach(fn() => CarbonImmutable::setTestNow());

it('/session сообщает об открытой сессии', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $telegramId = 111000030;
    User::factory()->create(['telegram_id' => $telegramId]);
    BarSession::factory()->create(['started_at' => now()]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearText('/session')
        ->reply()
        ->assertCalled('sendMessage');
});

it('cmd:session — bartender видит кнопку старта, когда бар открыт и сессии нет', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $telegramId = 111000031;
    User::factory()->bartender()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('cmd:session')
        ->reply()
        ->assertCalled('sendMessage');
});

it('session:start — bartender открывает сессию', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $telegramId = 111000032;
    User::factory()->bartender()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('session:start')
        ->reply()
        ->assertCalled('answerCallbackQuery');

    $this->assertDatabaseCount('bar_sessions', 1);
});

it('session:start — guest получает отказ доступа (CanManageMiddleware)', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $telegramId = 111000033;
    User::factory()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('session:start')
        ->reply()
        ->assertCalled('answerCallbackQuery');

    $this->assertDatabaseCount('bar_sessions', 0);
});
