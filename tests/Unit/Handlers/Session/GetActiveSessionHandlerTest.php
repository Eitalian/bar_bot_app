<?php

use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\BarSession;
use Carbon\CarbonImmutable;

afterEach(fn() => CarbonImmutable::setTestNow());

it('returns active session if it is still in window', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $session = BarSession::factory()->create(['started_at' => now()]);

    CarbonImmutable::setTestNow('2026-05-10 22:00:00');

    expect(app(GetActiveSessionHandler::class)->handle()?->id)
        ->toBe($session->id);
});

it('returns null if active session is past its window', function () {
    CarbonImmutable::setTestNow('2026-05-09 18:00:00');
    BarSession::factory()->create(['started_at' => now()]);

    CarbonImmutable::setTestNow('2026-05-10 13:00:00'); // следующий день, окно закрылось

    expect(app(GetActiveSessionHandler::class)->handle())->toBeNull();
});

it('returns null when no active session', function () {
    BarSession::factory()->closed()->create();

    expect(app(GetActiveSessionHandler::class)->handle())->toBeNull();
});
