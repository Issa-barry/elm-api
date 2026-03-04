<?php

namespace App\Enums;

enum SiteRole: string
{
    /** Propriétaire du siège (tous droits, tous sites) */
    case OWNER_SIEGE = 'owner_siege';

    /** Administrateur siège (lecture consolidée + gestion sites) */
    case ADMIN_SIEGE = 'admin_siege';

    /** Manager opérationnel d'un site spécifique */
    case MANAGER = 'manager';

    /** Staff opérationnel d'un site spécifique */
    case STAFF = 'staff';

    /** Lecture seule sur un site */
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

    /** Rôles qui confèrent les droits siège (accès tous sites) */
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
