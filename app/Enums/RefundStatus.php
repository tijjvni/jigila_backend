<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved  = 'approved';
    case Processed = 'processed';
    case Rejected  = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::Requested->value => 'Refund Requested',
            self::Approved->value  => 'Refund Approved',
            self::Processed->value => 'Refund Processed',
            self::Rejected->value  => 'Refund Rejected',
        ];
    }
}
