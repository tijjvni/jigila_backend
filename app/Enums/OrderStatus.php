<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Pickup     = 'pickup';
    case InTransit  = 'in_transit';
    case AtPort     = 'at_port';
    case OnVessel   = 'on_vessel';
    case Delivered  = 'delivered';
    case Cancelled  = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
