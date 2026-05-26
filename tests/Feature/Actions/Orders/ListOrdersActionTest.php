<?php

use App\Models\BarSession;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('GET /api/sessions/{id}/orders returns orders for session', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $user    = User::factory()->bartender()->create();
    $session = BarSession::factory()->create(['started_at' => now()]);
    Order::factory()->count(3)->create(['session_id' => $session->id]);

    $this->getJson("/api/sessions/{$session->id}/orders?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonCount(3);
});

it('GET returns 404 for unknown session', function () {
    $user = User::factory()->bartender()->create();

    $this->getJson("/api/sessions/9999/orders?telegram_id={$user->telegram_id}")
        ->assertNotFound();
});
