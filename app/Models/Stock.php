<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    protected $fillable = [
        'produit_id',
        'usine_id',
        'qte_stock',
        'seuil_alerte_stock',
    ];

    protected $casts = [
        'qte_stock'          => 'integer',
        'seuil_alerte_stock' => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function usine(): BelongsTo
    {
        return $this->belongsTo(Usine::class);
    }

    // ── Setters ───────────────────────────────────────────────────────────

    public function setQteStockAttribute($value): void
    {
        $this->attributes['qte_stock'] = max(0, (int) $value);
    }

    public function setSeuilAlerteStockAttribute($value): void
    {
        $this->attributes['seuil_alerte_stock'] = ($value === null || $value === '')
            ? null
            : max(0, (int) $value);
    }

    // ── Accesseurs calculés ───────────────────────────────────────────────

    public function getLowStockThresholdAttribute(): int
    {
        if (!is_null($this->seuil_alerte_stock)) {
            return $this->seuil_alerte_stock;
        }

        return Parametre::getSeuilStockFaible();
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->qte_stock <= 0;
    }

    public function getIsLowStockAttribute(): bool
    {
        if ($this->is_out_of_stock) {
            return false;
        }

        $seuil = $this->low_stock_threshold;

        return $seuil > 0 && $this->qte_stock <= $seuil;
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    /**
     * Ajuste le stock par un delta (positif = ajout, négatif = retrait).
     * Le stock ne peut pas passer en dessous de 0.
     */
    public function ajuster(int $quantite): static
    {
        $this->qte_stock = max(0, $this->qte_stock + $quantite);
        $this->save();

        return $this;
    }
}
