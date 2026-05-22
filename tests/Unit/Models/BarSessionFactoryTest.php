<?php

use App\Models\BarSession;

it('creates an active session by default', function () {
    $session = BarSession::factory()->create();

    expect($session->ended_at)->toBeNull()
        ->and($session->started_at)->not->toBeNull()
        ->and($session->bar_id)->toBe(1);
});

it('creates a closed session via state', function () {
    $session = BarSession::factory()->closed()->create();

    expect($session->ended_at)->not->toBeNull();
});

it('enforces single active session per bar via unique index', function () {
    BarSession::factory()->create();

    expect(fn () => BarSession::factory()->create())
        ->toThrow(\Illuminate\Database\QueryException::class);
});
