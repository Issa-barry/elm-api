<?php

namespace App\Enums;

enum StatutFactureVente: string
{
    case IMPAYEE  = 'impayee';
    case PARTIEL  = 'partiel';
    case PAYEE    = 'payee';
    case ANNULEE  = 'annulee';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
