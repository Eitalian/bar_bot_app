<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

afterEach(fn() => CarbonImmutable::setTestNow());

it('POST returns 201 for bartender, body contains created session', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $user = User::factory()->bartender()->create();

    $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertCreated()
        ->assertJsonPath('bar_id', 1)
        ->assertJsonPath('ended_at', null);
});

it('POST returns 403 for guest', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $user = User::factory()->create(); // guest по дефолту

    $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertForbidden();
});

it('POST is idempotent — returns existing active session on second call', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $user = User::factory()->bartender()->create();

    $first = $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")->json('id');
    $second = $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")->json('id');

    expect($first)->toBe($second);
});

it('POST returns 409 when bar is closed (BarClosedException)', function () {
    CarbonImmutable::setTestNow('2026-05-10 09:00:00'); // бар закрыт
    $user = User::factory()->bartender()->create();

    $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertStatus(409); // Conflict — domain rule violation
});

it('POST returns 404 when bar id does not match config', function () {
    $user = User::factory()->bartender()->create();

    $this->postJson("/api/bars/2/session?telegram_id={$user->telegram_id}")
        ->assertNotFound();
});
