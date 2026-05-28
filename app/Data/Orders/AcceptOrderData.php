<?php

namespace App\Data\Orders;

use Spatie\LaravelData\Data;

final class AcceptOrderData extends Data
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $quantity,
    ) {}
}
