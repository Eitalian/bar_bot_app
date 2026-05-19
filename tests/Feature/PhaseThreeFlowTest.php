<?php

use App\Jobs\CloseSessionJob;
use App\Models\BarSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

afterEach(fn() => CarbonImmutable::setTestNow());

it('full flow: open via API → see active via API → time travel → auto-closed', function () {
    Queue::fake();
    $user = User::factory()->bartender()->create();

    // 18:00 — открываем сессию
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $created = $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertCreated()
        ->json('id');

    // GET — активна
    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonPath('id', $created);

    // delayed CloseSessionJob запланирован
    Queue::assertPushed(CloseSessionJob::class, function (CloseSessionJob $job) use ($created) {
        return $job->sessionId === $created
            && $job->endAt->toIso8601String() === '2026-05-11T06:00:00+00:00';
    });

    // Симулируем выполнение job в 06:00 следующего дня
    CarbonImmutable::setTestNow('2026-05-11 06:00:00');
    (new CloseSessionJob($created, CarbonImmutable::parse('2026-05-11 06:00:00')))->handle();

    // GET — 204
    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertNoContent();
});

it('self-healing: stale session is closed when new start arrives', function () {
    Queue::fake();
    $user = User::factory()->bartender()->create();

    // Вчера 18:00 — старая сессия
    CarbonImmutable::setTestNow('2026-05-09 18:00:00');
    $stale = BarSession::factory()->create(['started_at' => now()]);

    // Сегодня 13:00 — открываем новую
    CarbonImmutable::setTestNow('2026-05-10 13:00:00');
    $newId = $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertCreated()
        ->json('id');

    // Старая закрыта, новая активна
    expect(BarSession::find($stale->id)->ended_at)->not->toBeNull()
        ->and(BarSession::find($newId)->ended_at)->toBeNull();
});
