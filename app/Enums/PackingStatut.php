<?php

namespace App\Enums;

enum PackingStatut: string
{
    case IMPAYEE = 'impayee';
    case PARTIELLE = 'partielle';
    case PAYEE = 'payee';
    case ANNULEE = 'annulee';

    public const LABELS = [
        self::IMPAYEE->value => 'Impayee',
        self::PARTIELLE->value => 'Partielle',
        self::PAYEE->value => 'Payee',
        self::ANNULEE->value => 'Annulee',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return self::LABELS;
    }

}
