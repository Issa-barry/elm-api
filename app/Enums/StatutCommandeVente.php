<?php

namespace App\Enums;

enum StatutCommandeVente: string
{
    case ACTIVE  = 'active';
    case ANNULEE = 'annulee';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
