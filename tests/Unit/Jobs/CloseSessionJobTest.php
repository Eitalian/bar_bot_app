<?php

use App\Jobs\CloseSessionJob;
use App\Models\BarSession;
use Carbon\CarbonImmutable;

it('closes an active session', function () {
    $session = BarSession::factory()->create();
    $endAt = CarbonImmutable::parse('2026-05-11 06:00:00');

    (new CloseSessionJob($session->id, $endAt))->handle();

    expect($session->fresh()->ended_at?->toIso8601String())
        ->toBe('2026-05-11T06:00:00+00:00');
});

it('is no-op when session is already closed', function () {
    $session = BarSession::factory()->closed()->create();
    $originalEnd = $session->ended_at;
    $endAt = CarbonImmutable::parse('2026-05-11 06:00:00');

    (new CloseSessionJob($session->id, $endAt))->handle();

    expect($session->fresh()->ended_at?->toIso8601String())
        ->toBe($originalEnd->toIso8601String());
});

it('is no-op when session does not exist', function () {
    $endAt = CarbonImmutable::parse('2026-05-11 06:00:00');

    expect(fn() => (new CloseSessionJob(9999, $endAt))->handle())
        ->not->toThrow(\Throwable::class);
});
