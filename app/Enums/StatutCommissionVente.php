<?php

namespace App\Enums;

enum StatutCommissionVente: string
{
    case IMPAYEE  = 'impayee';   // commission créée, aucun versement effectué
    case PARTIELLE = 'partielle'; // au moins un versement partiel effectué
    case PAYEE    = 'payee';     // tous les versements effectués
    case ANNULEE  = 'annulee';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
