<?php

namespace App\Data\Orders;

use Spatie\LaravelData\Data;

final class CancelOrderData extends Data
{
    public function __construct(
        public readonly int $orderId,
    ) {}
}
