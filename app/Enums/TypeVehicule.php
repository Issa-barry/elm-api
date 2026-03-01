<?php

namespace App\Enums;

enum TypeVehicule: string
{
    case CAMION    = 'camion';
    case VANNE     = 'vanne';
    case MOTO      = 'moto';
    case TRICYCLE  = 'tricycle';
    case PICK_UP   = 'pick_up';
    case AUTRE     = 'autre';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function allowedValues(): array
    {
        return self::values();
    }

    public static function normalize(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'tricyle' => self::TRICYCLE->value,
            default => $normalized,
        };
    }

    public static function defaultCapacitePacks(?string $type): ?int
    {
        return match (self::normalize($type)) {
            self::CAMION->value => 300,
            self::VANNE->value => 150,
            self::TRICYCLE->value => 70,
            default => null,
        };
    }
}
