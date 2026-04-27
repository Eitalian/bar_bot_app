<?php

namespace App\Enums;

enum UserRole: string
{
    case Guest     = 'guest';
    case Bartender = 'bartender';
    case Owner     = 'owner';

    public function canManage(): bool
    {
        return match ($this) {
            self::Bartender, self::Owner => true,
            self::Guest                  => false,
        };
    }
}
