<?php

use App\Data\Orders\CancelOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Handlers\Orders\CancelOrderHandler;
use App\Models\Order;

it('cancels a pending order', function () {
    $order = Order::factory()->create();

    $result = (new CancelOrderHandler)->handle(new CancelOrderData(orderId: $order->id));

    expect($result->status)->toBe(OrderStatus::Cancelled)
        ->and($result->relationLoaded('user'))->toBeTrue()
        ->and($result->relationLoaded('recipe'))->toBeTrue();
});

it('throws OrderAlreadyProcessedException when order is already accepted', function () {
    $order = Order::factory()->accepted()->create();

    expect(fn () => (new CancelOrderHandler)->handle(new CancelOrderData(orderId: $order->id)))
        ->toThrow(OrderAlreadyProcessedException::class);
});

it('throws OrderAlreadyProcessedException when order is already cancelled', function () {
    $order = Order::factory()->cancelled()->create();

    expect(fn () => (new CancelOrderHandler)->handle(new CancelOrderData(orderId: $order->id)))
        ->toThrow(OrderAlreadyProcessedException::class);
});
