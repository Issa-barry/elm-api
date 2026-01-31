<?php

namespace App\Enums;

enum ProduitStatut: string
{
    case BROUILLON = 'brouillon';
    case ACTIF = 'actif';
    case INACTIF = 'inactif';
    case ARCHIVE = 'archive';
    case RUPTURE_STOCK = 'rupture_stock';

    /**
     * Libellé en français
     */
    public function label(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::ACTIF => 'Actif',
            self::INACTIF => 'Inactif',
            self::ARCHIVE => 'Archivé',
            self::RUPTURE_STOCK => 'Rupture de stock',
        };
    }

    /**
     * Le produit est-il disponible à la vente ?
     */
    public function isAvailable(): bool
    {
        return $this === self::ACTIF;
    }

    /**
     * Transitions autorisées depuis ce statut
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::BROUILLON => [self::ACTIF, self::INACTIF, self::ARCHIVE],
            self::ACTIF => [self::INACTIF, self::ARCHIVE, self::RUPTURE_STOCK],
            self::INACTIF => [self::ACTIF, self::ARCHIVE],
            self::ARCHIVE => [self::ACTIF, self::INACTIF],
            self::RUPTURE_STOCK => [self::ACTIF, self::INACTIF, self::ARCHIVE],
        };
    }

    /**
     * Peut-on transitionner vers ce statut ?
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    /**
     * Toutes les valeurs
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
