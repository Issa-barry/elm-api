<?php

namespace App\Enums;

enum PieceType: string
{
    case CNI = 'cni';
    case PASSEPORT = 'passeport';
    case PERMIS = 'permis';
    case CARTE_SEJOUR = 'carte_sejour';

    public const LABELS = [
        self::CNI->value => 'Carte nationale d\'identité',
        self::PASSEPORT->value => 'Passeport',
        self::PERMIS->value => 'Permis de conduire',
        self::CARTE_SEJOUR->value => 'Carte de séjour',
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
