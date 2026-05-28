<?php

namespace App\Exceptions;

use RuntimeException;

final class OrderAlreadyProcessedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Заказ уже обработан');
    }
}
