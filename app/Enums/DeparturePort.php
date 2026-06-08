<?php

namespace App\Enums;

enum DeparturePort: string
{
    case HoustonTX    = 'houston_tx';
    case BaltimoreMD  = 'baltimore_md';
    case NewarkNJ     = 'newark_nj';
    case SavannahGA   = 'savannah_ga';
    case LosAngelesCA = 'los_angeles_ca';

    public function label(): string
    {
        return match($this) {
            self::HoustonTX    => 'Houston, TX',
            self::BaltimoreMD  => 'Baltimore, MD',
            self::NewarkNJ     => 'Newark, NJ',
            self::SavannahGA   => 'Savannah, GA',
            self::LosAngelesCA => 'Los Angeles, CA',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
