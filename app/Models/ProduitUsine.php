<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuration locale d'un produit pour un point de vente (usine).
 *
 * Chaque ligne indique si un produit est activé dans une usine donnée
 * et permet de surcharger les prix/coûts globaux du produit.
 */
class ProduitUsine extends Model
{
    protected $table = 'produit_sites';

    protected $fillable = [
        'produit_id',
        'site_id',
        'is_active',
        'prix_usine',
        'prix_achat',
        'prix_vente',
        'cout',
        'tva',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'prix_usine' => 'integer',
        'prix_achat' => 'integer',
        'prix_vente' => 'integer',
        'cout'       => 'integer',
        'tva'        => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    // ── Setters ───────────────────────────────────────────────────────────

    public function setPrixUsineAttribute($value): void
    {
        $this->attributes['prix_usine'] = $this->normalizePrice($value);
    }

    public function setPrixAchatAttribute($value): void
    {
        $this->attributes['prix_achat'] = $this->normalizePrice($value);
    }

    public function setPrixVenteAttribute($value): void
    {
        $this->attributes['prix_vente'] = $this->normalizePrice($value);
    }

    public function setCoutAttribute($value): void
    {
        $this->attributes['cout'] = $this->normalizePrice($value);
    }

    // ── Prix effectifs (local si défini, sinon global du produit) ─────────

    public function prixUsineEffectif(): ?int
    {
        return $this->prix_usine ?? $this->produit?->getAttributeValue('prix_usine');
    }

    public function prixAchatEffectif(): ?int
    {
        return $this->prix_achat ?? $this->produit?->getAttributeValue('prix_achat');
    }

    public function prixVenteEffectif(): ?int
    {
        return $this->prix_vente ?? $this->produit?->getAttributeValue('prix_vente');
    }

    public function coutEffectif(): ?int
    {
        return $this->cout ?? $this->produit?->getAttributeValue('cout');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function normalizePrice($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return max(0, (int) $value);
    }
}
