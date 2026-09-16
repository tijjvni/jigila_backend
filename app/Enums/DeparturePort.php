<?php

namespace App\Enums;

enum DeparturePort: string
{
    case BaltimoreMD        = 'baltimore_md';
    case DundalkBaltimoreMD = 'dundalk_baltimore_md';
    case NewarkNJ           = 'newark_nj';
    case PhiladelphiaPA     = 'philadelphia_pa';
    case WilmingtonDE       = 'wilmington_de';
    case ProvidenceRI       = 'providence_ri';
    case SavannahGA         = 'savannah_ga';
    case JacksonvilleFL     = 'jacksonville_fl';
    case MiamiFL            = 'miami_fl';
    case FreeportTX         = 'freeport_tx';

    public function label(): string
    {
        return match ($this) {
            self::BaltimoreMD        => 'Port of Baltimore, MD',
            self::DundalkBaltimoreMD => 'Dundalk Marine Terminal, Baltimore, MD',
            self::NewarkNJ           => 'Port of Newark / New York, NJ',
            self::PhiladelphiaPA     => 'Port of Philadelphia, PA',
            self::WilmingtonDE       => 'Port of Wilmington, DE',
            self::ProvidenceRI       => 'Port of Providence, RI',
            self::SavannahGA         => 'Port of Savannah, GA',
            self::JacksonvilleFL     => 'Port of Jacksonville (JAXPORT), FL',
            self::MiamiFL            => 'Port of Miami, FL',
            self::FreeportTX         => 'Port Freeport, TX',
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
