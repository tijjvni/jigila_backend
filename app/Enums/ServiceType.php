<?php

namespace App\Enums;

enum ServiceType: string
{
    case Trucking = 'trucking';
    case Shipping = 'shipping';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
