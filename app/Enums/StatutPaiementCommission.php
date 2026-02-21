<?php

namespace App\Enums;

enum StatutPaiementCommission: string
{
    case EN_ATTENTE = 'en_attente';
    case PAYE       = 'paye';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
