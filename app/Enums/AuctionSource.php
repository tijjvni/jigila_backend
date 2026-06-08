<?php

namespace App\Enums;

enum AuctionSource: string
{
    case Copart = 'Copart';
    case IAAI   = 'IAAI';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
