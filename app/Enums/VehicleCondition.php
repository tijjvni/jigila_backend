<?php

namespace App\Enums;

enum VehicleCondition: string
{
    case RunAndDrive = 'Run and Drive';
    case NonRunner   = 'Non-Runner';
    case Forklift    = 'Forklift';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
