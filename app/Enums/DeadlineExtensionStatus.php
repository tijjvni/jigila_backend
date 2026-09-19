<?php

namespace App\Enums;

/**
 * Customer-requested extension of a payment deadline.
 *
 * There is no `processed` step as there is for a refund — approval *is* the
 * action, because it moves `payment_due_at` there and then.
 */
enum DeadlineExtensionStatus: string
{
    case Requested = 'requested';
    case Approved  = 'approved';
    case Rejected  = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::Requested->value => 'Extension Requested',
            self::Approved->value  => 'Extension Approved',
            self::Rejected->value  => 'Extension Declined',
        ];
    }
}
