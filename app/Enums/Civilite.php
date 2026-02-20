<?php

namespace App\Enums;

enum Civilite: string
{
    case M = 'M';
    case MME = 'Mme';
    case MLLE = 'Mlle';

    public const LABELS = [
        self::M->value => 'Monsieur',
        self::MME->value => 'Madame',
        self::MLLE->value => 'Mademoiselle',
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
