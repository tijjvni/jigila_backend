<?php

namespace App\Enums;

/**
 * How a late fee accrues once a payment deadline has lapsed.
 *
 * The mode and its rate are frozen onto the invoice at issuance, so changing
 * the schedule in settings never re-prices an invoice that is already out.
 */
enum LateFeeMode: string
{
    case None    = 'none';
    case Flat    = 'flat';
    case Percent = 'percent';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::None->value    => 'No Late Fee',
            self::Flat->value    => 'Flat Amount Per Day',
            self::Percent->value => 'Percent of Invoice Per Day',
        ];
    }
}
