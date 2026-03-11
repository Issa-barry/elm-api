<?php

namespace App\Enums;

enum StatutVersementCommission: string
{
    case EN_ATTENTE          = 'en_attente';
    case PARTIELLEMENT_VERSE = 'partiellement_verse';
    case EFFECTUE            = 'effectue';   // alias : totalement versé
    case ANNULE              = 'annule';
}
