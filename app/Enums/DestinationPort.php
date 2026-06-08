<?php

namespace App\Enums;

enum DestinationPort: string
{
    case TinCanLagos = 'tin_can_lagos';
    case LagosApapa  = 'lagos_apapa';
    case TemaGhana   = 'tema_ghana';

    public function label(): string
    {
        return match($this) {
            self::TinCanLagos => 'Tin Can Island, Lagos',
            self::LagosApapa  => 'Apapa, Lagos',
            self::TemaGhana   => 'Tema, Ghana',
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
