<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE    = 'active';
    case TRIAL     = 'trial';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE    => 'Actif',
            self::TRIAL     => 'Période d\'essai',
            self::SUSPENDED => 'Suspendu',
            self::CANCELLED => 'Annulé',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
