<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Bid        = 'bid';
    case Service    = 'service';
    case BidDeposit = 'bid_deposit';
    case BidBalance = 'bid_balance';

    /**
     * Late payment fee billed against a parent invoice that went overdue. Kept
     * out of `service` so late-fee revenue never lands in the service column on
     * the dashboard.
     */
    case LateFee = 'late_fee';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
