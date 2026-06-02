<?php

use App\Data\Orders\AcceptOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Handlers\Orders\AcceptOrderHandler;
use App\Models\Order;

it('accepts a pending order with quantity', function () {
    $order = Order::factory()->create();

    $result = (new AcceptOrderHandler)->handle(new AcceptOrderData(
        orderId:  $order->id,
        quantity: 3,
    ));

    expect($result->status)->toBe(OrderStatus::Accepted)
        ->and($result->quantity)->toBe(3)
        ->and($result->relationLoaded('user'))->toBeTrue()
        ->and($result->relationLoaded('recipe'))->toBeTrue();
});

it('throws OrderAlreadyProcessedException when order is already accepted', function () {
    $order = Order::factory()->accepted()->create();

    expect(fn () => (new AcceptOrderHandler)->handle(new AcceptOrderData(
        orderId:  $order->id,
        quantity: 2,
    )))->toThrow(OrderAlreadyProcessedException::class);
});

it('throws OrderAlreadyProcessedException when order is cancelled', function () {
    $order = Order::factory()->cancelled()->create();

    expect(fn () => (new AcceptOrderHandler)->handle(new AcceptOrderData(
        orderId:  $order->id,
        quantity: 1,
    )))->toThrow(OrderAlreadyProcessedException::class);
});
