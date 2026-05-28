<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Cancelled = 'cancelled';
}
