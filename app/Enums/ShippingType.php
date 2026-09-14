<?php

namespace App\Enums;

enum ShippingType: string
{
    case RoRo      = 'roro';
    case Container = 'container';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::RoRo->value      => 'RoRo',
            self::Container->value => 'Container',
        ];
    }
}
