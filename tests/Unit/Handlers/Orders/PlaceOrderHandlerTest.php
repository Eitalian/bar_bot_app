<?php

use App\Data\Orders\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Handlers\Orders\PlaceOrderHandler;
use App\Models\BarSession;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('creates a pending order in active session', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $session = BarSession::factory()->create(['started_at' => now()]);
    $user    = User::factory()->create();
    $recipe  = Recipe::factory()->create();

    $order = app(PlaceOrderHandler::class)->handle(
        new PlaceOrderData(recipeId: $recipe->id, userId: $user->id)
    );

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->session_id)->toBe($session->id)
        ->and($order->user_id)->toBe($user->id)
        ->and($order->recipe_id)->toBe($recipe->id)
        ->and($order->quantity)->toBeNull();
});

it('throws RuntimeException when no active session', function () {
    $user   = User::factory()->create();
    $recipe = Recipe::factory()->create();

    expect(fn () => app(PlaceOrderHandler::class)->handle(
        new PlaceOrderData(recipeId: $recipe->id, userId: $user->id)
    ))->toThrow(\RuntimeException::class);
});
