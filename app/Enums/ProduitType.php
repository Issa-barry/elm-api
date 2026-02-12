<?php

namespace App\Enums;

enum ProduitType: string
{
    case MATERIEL = 'materiel';
    case SERVICE = 'service';
    case FABRICABLE = 'fabricable';
    case ACHAT_VENTE = 'achat_vente';

    /**
     * Libellé en français
     */
    public function label(): string
    {
        return match ($this) {
            self::MATERIEL => 'Matériel',
            self::SERVICE => 'Service',
            self::FABRICABLE => 'Fabricable',
            self::ACHAT_VENTE => 'Achat/Vente',
        };
    }

    /**
     * Le stock est-il pertinent pour ce type ?
     */
    public function hasStock(): bool
    {
        return match ($this) {
            self::SERVICE => false,
            default => true,
        };
    }

    /**
     * Prix obligatoires selon le type
     */
    public function requiredPrices(): array
    {
        return match ($this) {
            self::MATERIEL => ['prix_achat'],
            // Cas géré par validation dédiée: service = prix_achat ou prix_vente
            self::SERVICE => [],
            self::FABRICABLE => ['prix_usine', 'prix_vente'],
            self::ACHAT_VENTE => ['prix_achat', 'prix_vente'],
        };
    }

    /**
     * Toutes les valeurs
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
