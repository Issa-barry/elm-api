<?php

namespace App\Enums;

enum StatutFactureLivraison: string
{
    case BROUILLON         = 'brouillon';
    case EMISE             = 'emise';
    case PARTIELLEMENT_PAYEE = 'partiellement_payee';
    case PAYEE             = 'payee';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
