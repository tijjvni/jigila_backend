<?php

namespace App\Enums;

enum DestinationPort: string
{
    case LagosApapa         = 'lagos_apapa';
    case TinCanLagos        = 'tin_can_lagos';
    case TemaGhana          = 'tema_ghana';
    case LomeTogo           = 'lome_togo';
    case CotonouBenin       = 'cotonou_benin';
    case AbidjanIvoryCoast  = 'abidjan_ivory_coast';
    case DakarSenegal       = 'dakar_senegal';
    case ConakryGuinea      = 'conakry_guinea';
    case FreetownSierraLeone = 'freetown_sierra_leone';
    case MonroviaLiberia    = 'monrovia_liberia';
    case BanjulGambia       = 'banjul_gambia';
    case BissauGuineaBissau = 'bissau_guinea_bissau';

    public function label(): string
    {
        return match ($this) {
            self::LagosApapa          => 'Port of Apapa, Lagos – Nigeria',
            self::TinCanLagos         => 'Tin Can Island Port, Lagos – Nigeria',
            self::TemaGhana           => 'Port of Tema, Accra – Ghana',
            self::LomeTogo            => 'Port of Lomé, Togo',
            self::CotonouBenin        => 'Port of Cotonou, Benin',
            self::AbidjanIvoryCoast   => 'Port of Abidjan, Côte d\'Ivoire',
            self::DakarSenegal        => 'Port of Dakar, Senegal',
            self::ConakryGuinea       => 'Port of Conakry, Guinea',
            self::FreetownSierraLeone => 'Port of Freetown, Sierra Leone',
            self::MonroviaLiberia     => 'Port of Monrovia (Freeport), Liberia',
            self::BanjulGambia        => 'Port of Banjul, Gambia',
            self::BissauGuineaBissau  => 'Port of Bissau, Guinea-Bissau',
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
