<?php

namespace App\Enums;

enum OrganisationStatut: string
{
    case ACTIVE    = 'active';
    case INACTIVE  = 'inactive';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE    => 'Active',
            self::INACTIVE  => 'Inactive',
            self::SUSPENDED => 'Suspendue',
        };
    }
}
