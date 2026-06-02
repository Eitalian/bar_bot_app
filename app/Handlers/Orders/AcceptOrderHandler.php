<?php

namespace App\Handlers\Orders;

use App\Data\Orders\AcceptOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Models\Order;

final class AcceptOrderHandler
{
    public function handle(AcceptOrderData $data): Order
    {
        $order = Order::findOrFail($data->orderId);

        if ($order->status !== OrderStatus::Pending) {
            throw new OrderAlreadyProcessedException;
        }

        $order->update([
            'status'   => OrderStatus::Accepted,
            'quantity' => $data->quantity,
        ]);

        return $order->fresh(['user', 'recipe']);
    }
}
