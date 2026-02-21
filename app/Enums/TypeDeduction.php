<?php

namespace App\Enums;

enum TypeDeduction: string
{
    case CARBURANT  = 'carburant';
    case REPARATION = 'reparation';
    case AVANCE     = 'avance';
    case AUTRE      = 'autre';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
