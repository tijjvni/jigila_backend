<?php

namespace App\Enums;

enum ShippingLine: string
{
    case Sallaum  = 'sallaum';
    case Grimaldi = 'grimaldi';
    case Maersk   = 'maersk';
    case CmaCgm   = 'cma_cgm';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::Sallaum->value  => 'Sallaum Lines',
            self::Grimaldi->value => 'Grimaldi Lines',
            self::Maersk->value   => 'Maersk',
            self::CmaCgm->value   => 'CMA CGM',
        ];
    }
}
