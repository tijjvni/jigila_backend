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

    /**
     * Payment deadline lapsed and the shipment is on hold. Deliberately last —
     * `ConfigController` zips `values()` positionally against its label list.
     */
    case PaymentOverdue = 'payment_overdue';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses an admin may set by hand.
     *
     * `payment_overdue` is system-owned: the deadline sweep writes it together
     * with `shipment_hold` and `status_before_hold`, and payment or an approved
     * extension clears all three. Letting an admin set it directly would leave a
     * hold with nothing recorded to restore, so it is excluded here and released
     * through `POST admin/orders/{order}/release-hold` instead.
     */
    public static function assignableValues(): array
    {
        return array_values(array_diff(self::values(), [self::PaymentOverdue->value]));
    }
}
