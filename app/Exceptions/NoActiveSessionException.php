<?php

namespace App\Exceptions;

use RuntimeException;

final class NoActiveSessionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Нет активной бар-сессии');
    }
}
