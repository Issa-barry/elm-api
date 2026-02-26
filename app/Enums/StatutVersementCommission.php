<?php

namespace App\Enums;

enum StatutVersementCommission: string
{
    case EN_ATTENTE = 'en_attente';
    case EFFECTUE   = 'effectue';
    case ANNULE     = 'annule';
}
