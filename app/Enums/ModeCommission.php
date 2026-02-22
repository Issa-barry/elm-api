<?php

namespace App\Enums;

enum ModeCommission: string
{
    case FORFAIT     = 'forfait';
    case POURCENTAGE = 'pourcentage';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
