<?php

namespace App\Handlers\Orders;

use App\Data\Orders\CancelOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Models\Order;

final class CancelOrderHandler
{
    public function handle(CancelOrderData $data): Order
    {
        $order = Order::findOrFail($data->orderId);

        if ($order->status !== OrderStatus::Pending) {
            throw new OrderAlreadyProcessedException;
        }

        $order->update(['status' => OrderStatus::Cancelled]);

        return $order->fresh(['user', 'recipe']);
    }
}
