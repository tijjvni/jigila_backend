<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Bid        = 'bid';
    case Service    = 'service';
    case BidDeposit = 'bid_deposit';
    case BidBalance = 'bid_balance';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
