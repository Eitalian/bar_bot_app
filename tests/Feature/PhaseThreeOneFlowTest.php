<?php

use App\Enums\OrderStatus;
use App\Models\BarSession;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('full flow: place → accept via HTTP → order accepted', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $bartender = User::factory()->bartender()->create();
    $guest     = User::factory()->create();
    $session   = BarSession::factory()->create(['started_at' => now()]);
    $recipe    = Recipe::factory()->create();

    $order = Order::factory()->create([
        'session_id' => $session->id,
        'user_id'    => $guest->id,
        'recipe_id'  => $recipe->id,
    ]);

    expect($order->status)->toBe(OrderStatus::Pending);

    $this->patchJson("/api/orders/{$order->id}/accept?telegram_id={$bartender->telegram_id}", [
        'quantity' => 3,
    ])->assertOk()->assertJsonPath('status', 'accepted');

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted)
        ->and($order->fresh()->quantity)->toBe(3);
});

it('PATCH /accept returns 409 when order already processed', function () {
    $bartender = User::factory()->bartender()->create();
    $order     = Order::factory()->accepted()->create();

    $this->patchJson("/api/orders/{$order->id}/accept?telegram_id={$bartender->telegram_id}", [
        'quantity' => 1,
    ])->assertStatus(409);
});

it('PATCH /cancel returns 409 when order already processed', function () {
    $bartender = User::factory()->bartender()->create();
    $order     = Order::factory()->accepted()->create();

    $this->patchJson("/api/orders/{$order->id}/cancel?telegram_id={$bartender->telegram_id}")
        ->assertStatus(409);
});

it('PATCH /accept returns 403 when caller is a guest', function () {
    $guest = User::factory()->create();
    $order = Order::factory()->create();

    $this->patchJson("/api/orders/{$order->id}/accept?telegram_id={$guest->telegram_id}", [
        'quantity' => 1,
    ])->assertForbidden();
});

it('PATCH /cancel returns 403 when caller is a guest', function () {
    $guest = User::factory()->create();
    $order = Order::factory()->create();

    $this->patchJson("/api/orders/{$order->id}/cancel?telegram_id={$guest->telegram_id}")
        ->assertForbidden();
});

it('GET /api/sessions/{id}/orders returns all non-cancelled for bartender', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $bartender = User::factory()->bartender()->create();
    $session   = BarSession::factory()->create(['started_at' => now()]);

    Order::factory()->count(2)->create(['session_id' => $session->id]);
    Order::factory()->cancelled()->create(['session_id' => $session->id]);

    $this->getJson("/api/sessions/{$session->id}/orders?telegram_id={$bartender->telegram_id}")
        ->assertOk()
        ->assertJsonCount(2);
});

it('GET /api/sessions/{id}/orders returns only own orders for guest', function () {
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

it('pending orders appear last in sort', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $bartender = User::factory()->bartender()->create();
    $session   = BarSession::factory()->create(['started_at' => now()]);

    Order::factory()->create(['session_id' => $session->id]);
    Order::factory()->accepted()->create(['session_id' => $session->id]);

    $this->getJson("/api/sessions/{$session->id}/orders?telegram_id={$bartender->telegram_id}")
        ->assertOk()
        ->assertJsonPath('0.status', 'accepted')
        ->assertJsonPath('1.status', 'pending');
});
