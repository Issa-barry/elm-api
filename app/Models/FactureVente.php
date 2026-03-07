<?php

namespace App\Models;

use App\Enums\StatutFactureVente;
use App\Models\Traits\HasSiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FactureVente extends Model
{
    use HasFactory, SoftDeletes, HasSiteScope;

    private const TEMP_REFERENCE_PREFIX = 'TMP-FAC-VNT-';

    protected $table = 'factures_ventes';

    protected $fillable = [
        'site_id',
        'vehicule_id',
        'commande_vente_id',
        'reference',
        'montant_brut',
        'montant_net',
        'statut_facture',
    ];

    protected function casts(): array
    {
        return [
            'montant_brut'   => 'decimal:2',
            'montant_net'    => 'decimal:2',
            'statut_facture' => StatutFactureVente::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FactureVente $facture) {
            if (empty($facture->reference)) {
                // Placeholder unique pour passer la contrainte NOT NULL/UNIQUE avant d'avoir l'id réel.
                $facture->reference = self::TEMP_REFERENCE_PREFIX . Str::uuid();
            }
        });

        static::created(function (FactureVente $facture): void {
            if (!str_starts_with((string) $facture->reference, self::TEMP_REFERENCE_PREFIX)) {
                return;
            }

            $datePart       = ($facture->created_at ?? now())->format('Ymd');
            $finalReference = 'FAC-VNT-' . $datePart . '-' . str_pad((string) $facture->id, 4, '0', STR_PAD_LEFT);

            static::withoutEvents(function () use ($facture, $finalReference): void {
                $facture->newQueryWithoutScopes()
                    ->whereKey($facture->id)
                    ->update(['reference' => $finalReference]);
            });

            $facture->reference = $finalReference;
            $facture->syncOriginalAttribute('reference');
        });
    }

    public function getMontantEncaisseAttribute(): float
    {
        return (float) $this->encaissements()->sum('montant');
    }

    public function getMontantRestantAttribute(): float
    {
        return max(0, (float) $this->montant_net - $this->montant_encaisse);
    }

    protected $appends = ['montant_encaisse', 'montant_restant'];

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(CommandeVente::class, 'commande_vente_id');
    }

    public function encaissements(): HasMany
    {
        return $this->hasMany(EncaissementVente::class, 'facture_vente_id');
    }

    /**
     * Recalcule et persiste le statut facture selon le cumul encaissé.
     * Bloque si la facture est annulée.
     */
    public function recalculStatut(): void
    {
        if ($this->statut_facture === StatutFactureVente::ANNULEE) {
            return;
        }

        $encaisse = $this->encaissements()->sum('montant');
        $net      = (float) $this->montant_net;

        if ($encaisse <= 0) {
            $statut = StatutFactureVente::IMPAYEE;
        } elseif ($encaisse >= $net) {
            $statut = StatutFactureVente::PAYEE;
        } else {
            $statut = StatutFactureVente::PARTIEL;
        }

        $this->update(['statut_facture' => $statut]);
    }

    public function isAnnulee(): bool
    {
        return $this->statut_facture === StatutFactureVente::ANNULEE;
    }

    public function isPayee(): bool
    {
        return $this->statut_facture === StatutFactureVente::PAYEE;
    }
}
