<?php

use App\Handlers\Orders\ListGuestOrdersHandler;
use App\Models\BarSession;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('returns only own orders for active session, pending last', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $session = BarSession::factory()->create(['started_at' => now()]);
    $user    = User::factory()->create();

    Order::factory()->create(['session_id' => $session->id, 'user_id' => $user->id]);
    Order::factory()->accepted()->create(['session_id' => $session->id, 'user_id' => $user->id]);
    Order::factory()->create(['session_id' => $session->id]); // другой гость — не должен попасть

    $result = app(ListGuestOrdersHandler::class)->handle($user->id);

    expect($result)->toHaveCount(2);
    expect($result->first()->status->value)->toBe('accepted');
    expect($result->last()->status->value)->toBe('pending');
});

it('returns empty collection when no active session', function () {
    $user = User::factory()->create();

    expect(app(ListGuestOrdersHandler::class)->handle($user->id))->toBeEmpty();
});

it('returns empty collection when user has no orders in active session', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    BarSession::factory()->create(['started_at' => now()]);
    $user = User::factory()->create();

    expect(app(ListGuestOrdersHandler::class)->handle($user->id))->toBeEmpty();
});
