<?php

use App\Handlers\Orders\ListSessionOrdersHandler;
use App\Models\BarSession;
use App\Models\Order;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('returns all non-cancelled orders for session, pending last', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $session = BarSession::factory()->create(['started_at' => now()]);

    Order::factory()->create(['session_id' => $session->id]);
    Order::factory()->accepted()->create(['session_id' => $session->id]);
    Order::factory()->cancelled()->create(['session_id' => $session->id]);

    $result = app(ListSessionOrdersHandler::class)->handle($session->id);

    expect($result)->toHaveCount(2);
    expect($result->first()->status->value)->toBe('accepted');
    expect($result->last()->status->value)->toBe('pending');
});

it('excludes orders from other sessions', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $session1 = BarSession::factory()->create(['started_at' => now()]);
    $session2 = BarSession::factory()->create(['started_at' => now()->subDay(), 'ended_at' => now()->subHour()]);

    Order::factory()->count(2)->create(['session_id' => $session1->id]);
    Order::factory()->create(['session_id' => $session2->id]);

    $result = app(ListSessionOrdersHandler::class)->handle($session1->id);

    expect($result)->toHaveCount(2);
});
