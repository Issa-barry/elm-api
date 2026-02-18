<?php

namespace App\Enums;

enum PrestataireType: string
{
    case MACHINISTE = 'machiniste';
    case MECANICIEN = 'mecanicien';
    case CONSULTANT = 'consultant';
    case FOURNISSEUR = 'fournisseur';

    public const LABELS = [
        self::MACHINISTE->value => 'Machiniste',
        self::MECANICIEN->value => 'Mecanicien',
        self::CONSULTANT->value => 'Consultant',
        self::FOURNISSEUR->value => 'Fournisseur',
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
