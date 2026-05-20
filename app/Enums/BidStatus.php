<?php

namespace App\Enums;

enum BidStatus: string
{
    case Pending = 'pending';
    case Won     = 'won';
    case Lost    = 'lost';
    case OutBid  = 'out_bid';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
