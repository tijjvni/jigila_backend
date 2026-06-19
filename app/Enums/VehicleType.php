<?php

namespace App\Enums;

enum VehicleType: string
{
    case Hatchback     = 'hatchback';
    case Sedan         = 'sedan';
    case Coupe         = 'coupe';
    case MidSUV        = 'mid_suv';
    case FullSUV       = 'full_suv';
    case LuxurySUV     = 'lux_suv';
    case Minivan       = 'minivan';
    case PickupStd     = 'pickup_std';
    case PickupFull    = 'pickup_full';
    case CommercialVan = 'commercial_van';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
