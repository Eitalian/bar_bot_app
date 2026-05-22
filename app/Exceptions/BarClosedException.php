<?php

namespace App\Exceptions;

use RuntimeException;

final class BarClosedException extends RuntimeException
{
    public function __construct(string $message = 'Бар сейчас не работает')
    {
        parent::__construct($message);
    }
}
