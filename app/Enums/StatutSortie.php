<?php

namespace App\Enums;

enum StatutSortie: string
{
    case EN_COURS = 'en_cours';
    case RETOURNE = 'retourne';
    case CLOTURE  = 'cloture';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
