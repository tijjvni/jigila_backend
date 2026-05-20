<?php

namespace App\Enums;

enum VehicleCondition: string
{
    case Runner          = 'Runner';
    case RunsAndDrives   = 'Runs and drives';
    case EnhancedVehicle = 'Enhanced vehicle';
    case Stationary      = 'Stationary';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
