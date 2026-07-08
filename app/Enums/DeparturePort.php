<?php

namespace App\Enums;

enum DeparturePort: string
{
    case BaltimoreMD    = 'baltimore_md';
    case NewarkNJ       = 'newark_nj';
    case HoustonTX      = 'houston_tx';
    case SavannahGA     = 'savannah_ga';
    case JacksonvilleFL = 'jacksonville_fl';
    case MiamiFL        = 'miami_fl';
    case CharlestonSC   = 'charleston_sc';
    case LosAngelesCA   = 'los_angeles_ca';
    case NorfolkVA      = 'norfolk_va';
    case PortArthurTX   = 'port_arthur_tx';

    public function label(): string
    {
        return match ($this) {
            self::BaltimoreMD    => 'Port of Baltimore, MD',
            self::NewarkNJ       => 'Port of Newark / New York, NJ',
            self::HoustonTX      => 'Port of Houston (Barbours Cut), TX',
            self::SavannahGA     => 'Port of Savannah, GA',
            self::JacksonvilleFL => 'Port of Jacksonville (JAXPORT), FL',
            self::MiamiFL        => 'Port of Miami, FL',
            self::CharlestonSC   => 'Port of Charleston, SC',
            self::LosAngelesCA   => 'Port of Los Angeles / Long Beach, CA',
            self::NorfolkVA      => 'Port of Norfolk (Virginia International), VA',
            self::PortArthurTX   => 'Port of Port Arthur / Orange, TX',
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
