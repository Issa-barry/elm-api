<?php

namespace App\Enums;

enum UserType: string
{
    case STAFF = 'staff';
    case CLIENT = 'client';
    case PRESTATAIRE = 'prestataire';
    case INVESTISSEUR = 'investisseur';

    public const LABELS = [
        self::STAFF->value => 'Staff',
        self::CLIENT->value => 'Client',
        self::PRESTATAIRE->value => 'Prestataire',
        self::INVESTISSEUR->value => 'Investisseur',
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
