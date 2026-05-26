<?php

namespace App\Handlers\Orders;

use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\Order;
use Illuminate\Support\Collection;

final class ListGuestOrdersHandler
{
    public function __construct(
        private readonly GetActiveSessionHandler $sessionHandler,
    ) {}

    /** @return Collection<int, Order> */
    public function handle(int $userId): Collection
    {
        $session = $this->sessionHandler->handle();

        if ($session === null) {
            return collect();
        }

        return Order::with('recipe')
            ->where('session_id', $session->id)
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->get();
    }
}
