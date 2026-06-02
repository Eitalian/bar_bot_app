<?php

namespace App\Handlers\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Collection;

final class ListSessionOrdersHandler
{
    /** @return Collection<int, Order> */
    public function handle(int $sessionId): Collection
    {
        return Order::with('recipe', 'user')
            ->where('session_id', $sessionId)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->orderByRaw("CASE status WHEN 'accepted' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get();
    }
}
