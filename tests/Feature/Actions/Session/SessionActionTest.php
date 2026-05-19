<?php

use App\Models\BarSession;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn() => CarbonImmutable::setTestNow());

it('GET returns 200 with active session payload', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $user = User::factory()->create();
    $session = BarSession::factory()->create(['started_at' => now()]);

    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonPath('id', $session->id)
        ->assertJsonPath('bar_id', 1)
        ->assertJsonPath('ended_at', null);
})->skip('routes registered in T10');

it('GET returns 204 when no active session', function () {
    $user = User::factory()->create();

    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertNoContent();
})->skip('routes registered in T10');

it('GET returns 204 when session exists but is past its window', function () {
    CarbonImmutable::setTestNow('2026-05-09 18:00:00');
    $user = User::factory()->create();
    BarSession::factory()->create(['started_at' => now()]);
    CarbonImmutable::setTestNow('2026-05-10 13:00:00');

    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertNoContent();
})->skip('routes registered in T10');

it('GET returns 404 when bar id does not match config', function () {
    $user = User::factory()->create();

    $this->getJson("/api/bars/2/session?telegram_id={$user->telegram_id}")
        ->assertNotFound();
})->skip('routes registered in T10');
