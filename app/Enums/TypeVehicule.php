<?php

namespace App\Enums;

enum TypeVehicule: string
{
    case CAMION    = 'camion';
    case MOTO      = 'moto';
    case TRICYCLE  = 'tricycle';
    case PICK_UP   = 'pick_up';
    case AUTRE     = 'autre';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
