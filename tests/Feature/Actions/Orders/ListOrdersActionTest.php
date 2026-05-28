<?php

use App\Models\BarSession;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('bartender sees all non-cancelled orders for session', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $bartender = User::factory()->bartender()->create();
    $session   = BarSession::factory()->create(['started_at' => now()]);
    Order::factory()->count(3)->create(['session_id' => $session->id]);
    Order::factory()->cancelled()->create(['session_id' => $session->id]);

    $this->getJson("/api/sessions/{$session->id}/orders?telegram_id={$bartender->telegram_id}")
        ->assertOk()
        ->assertJsonCount(3);
});

it('guest sees only own orders for session', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $guest   = User::factory()->create();
    $other   = User::factory()->create();
    $session = BarSession::factory()->create(['started_at' => now()]);

    Order::factory()->count(2)->create(['session_id' => $session->id, 'user_id' => $guest->id]);
    Order::factory()->create(['session_id' => $session->id, 'user_id' => $other->id]);

    $this->getJson("/api/sessions/{$session->id}/orders?telegram_id={$guest->telegram_id}")
        ->assertOk()
        ->assertJsonCount(2);
});

it('returns 404 for unknown session', function () {
    $user = User::factory()->bartender()->create();

    $this->getJson("/api/sessions/9999/orders?telegram_id={$user->telegram_id}")
        ->assertNotFound();
});
