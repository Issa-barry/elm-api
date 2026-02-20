<?php

namespace App\Enums;

enum UsineRole: string
{
    /** Propriétaire du siège (tous droits, toutes usines) */
    case OWNER_SIEGE = 'owner_siege';

    /** Administrateur siège (lecture consolidée + gestion usines) */
    case ADMIN_SIEGE = 'admin_siege';

    /** Manager opérationnel d'une usine spécifique */
    case MANAGER = 'manager';

    /** Staff opérationnel d'une usine spécifique */
    case STAFF = 'staff';

    /** Lecture seule sur une usine */
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::OWNER_SIEGE => 'Propriétaire Siège',
            self::ADMIN_SIEGE => 'Administrateur Siège',
            self::MANAGER     => 'Manager',
            self::STAFF       => 'Staff',
            self::VIEWER      => 'Lecteur',
        };
    }

    /** Rôles qui confèrent les droits siège (accès toutes usines) */
    public function isSiegeRole(): bool
    {
        return in_array($this, [self::OWNER_SIEGE, self::ADMIN_SIEGE], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn ($c) => $c->label(), self::cases())
        );
    }

    public static function siegeRoles(): array
    {
        return [self::OWNER_SIEGE->value, self::ADMIN_SIEGE->value];
    }
}
