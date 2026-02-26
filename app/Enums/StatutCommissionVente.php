<?php

namespace App\Enums;

enum StatutCommissionVente: string
{
    case EN_ATTENTE          = 'en_attente';
    case ELIGIBLE            = 'eligible';
    case PARTIELLEMENT_VERSEE = 'partiellement_versee';
    case VERSEE              = 'versee';
    case ANNULEE             = 'annulee';
}
