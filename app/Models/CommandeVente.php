<?php

namespace App\Models;

use App\Enums\StatutCommandeVente;
use App\Models\Traits\HasSiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CommandeVente extends Model
{
    use HasFactory, SoftDeletes, HasSiteScope;

    private const TEMP_REFERENCE_PREFIX = 'TMP-VNT-';

    protected $table = 'commandes_ventes';

    protected $fillable = [
        'site_id',
        'vehicule_id',
        'reference',
        'total_commande',
        'created_by',
        'updated_by',
        'statut',
        'motif_annulation',
        'annulee_at',
        'annulee_par',
    ];

    protected function casts(): array
    {
        return [
            'total_commande' => 'decimal:2',
            'statut'         => StatutCommandeVente::class,
            'annulee_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommandeVente $commande) {
            if (empty($commande->reference)) {
                // Placeholder unique pour passer la contrainte NOT NULL/UNIQUE avant d'avoir l'id réel.
                $commande->reference = self::TEMP_REFERENCE_PREFIX . Str::uuid();
            }
        });

        static::created(function (CommandeVente $commande): void {
            if (!str_starts_with((string) $commande->reference, self::TEMP_REFERENCE_PREFIX)) {
                return;
            }

            $datePart       = ($commande->created_at ?? now())->format('Ymd');
            $finalReference = 'VNT-' . $datePart . '-' . str_pad((string) $commande->id, 4, '0', STR_PAD_LEFT);

            static::withoutEvents(function () use ($commande, $finalReference): void {
                $commande->newQueryWithoutScopes()
                    ->whereKey($commande->id)
                    ->update(['reference' => $finalReference]);
            });

            $commande->reference = $finalReference;
            $commande->syncOriginalAttribute('reference');
        });
    }

    public function isAnnulee(): bool
    {
        return $this->statut === StatutCommandeVente::ANNULEE;
    }

    /**
     * Clôture la commande si toutes les obligations financières sont soldées :
     *  - facture payée
     *  - commission payée (ou absente)
     */
    public function cloturerSiComplete(): void
    {
        if ($this->statut === StatutCommandeVente::ANNULEE) {
            return;
        }

        $facture = $this->facture()->withoutGlobalScopes()->first();

        if (! $facture || ! $facture->isPayee()) {
            return;
        }

        $commission = $this->commission()->withoutGlobalScopes()->first();

        if ($commission && $commission->statut !== \App\Enums\StatutCommissionVente::PAYEE) {
            return;
        }

        $this->update(['statut' => StatutCommandeVente::CLOTUREE]);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function annuleePar(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'annulee_par');
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CommandeVenteLigne::class, 'commande_vente_id');
    }

    public function facture(): HasOne
    {
        return $this->hasOne(FactureVente::class, 'commande_vente_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(CommissionVente::class, 'commande_vente_id');
    }
}
