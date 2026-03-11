<?php

namespace App\Enums;

enum StatutCommandeVente: string
{
    case ACTIVE   = 'active';
    case ANNULEE  = 'annulee';
    case CLOTUREE = 'cloturee'; // facture payée + commission versée (ou pas de commission)

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
