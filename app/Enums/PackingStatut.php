<?php

namespace App\Enums;

enum PackingStatut: string
{
    case A_VALIDER = 'a_valider';
    case VALIDE = 'valide';
    case ANNULE = 'annule';

    public const LABELS = [
        self::A_VALIDER->value => 'A valider',
        self::VALIDE->value => 'Valide',
        self::ANNULE->value => 'Annule',
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
