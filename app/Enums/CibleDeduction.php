<?php

namespace App\Enums;

enum CibleDeduction: string
{
    case PROPRIETAIRE = 'proprietaire';
    case LIVREUR      = 'livreur';
    case USINE        = 'usine';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
