<?php

namespace App\Handlers\Orders;

use App\Data\Orders\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\NoActiveSessionException;
use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\Order;

final class PlaceOrderHandler
{
    public function __construct(
        private readonly GetActiveSessionHandler $sessionHandler,
    ) {}

    public function handle(PlaceOrderData $data): Order
    {
        $session = $this->sessionHandler->handle();

        if ($session === null) {
            throw new NoActiveSessionException;
        }

        return Order::create([
            'session_id' => $session->id,
            'user_id'    => $data->userId,
            'recipe_id'  => $data->recipeId,
            'status'     => OrderStatus::Pending,
            'quantity'   => $data->quantity,
        ]);
    }
}
