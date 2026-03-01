<?php

namespace App\Enums;

enum ModePaiement: string
{
    case ESPECES      = 'especes';
    case MOBILE_MONEY = 'mobile_money';
    case VIREMENT     = 'virement';
    case CHEQUE       = 'cheque';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
