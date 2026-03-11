<?php

namespace App\Models;

use App\Enums\StatutCommissionVente;
use App\Enums\StatutVersementCommission;
use App\Models\Traits\HasSiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionVente extends Model
{
    use HasFactory, SoftDeletes, HasSiteScope;

    protected $table = 'commission_ventes';

    protected $fillable = [
        'site_id',
        'commande_vente_id',
        'vehicule_id',
        'livreur_id',
        'proprietaire_id',
        'taux_livreur_snapshot',
        'montant_commission_total',
        'part_livreur',
        'part_proprietaire',
        'statut',
        'eligible_at',
    ];

    protected function casts(): array
    {
        return [
            'taux_livreur_snapshot'    => 'decimal:2',
            'montant_commission_total' => 'decimal:2',
            'part_livreur'             => 'decimal:2',
            'part_proprietaire'        => 'decimal:2',
            'statut'                   => StatutCommissionVente::class,
            'eligible_at'              => 'datetime',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(CommandeVente::class, 'commande_vente_id');
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(Livreur::class);
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Proprietaire::class);
    }

    public function versements(): HasMany
    {
        return $this->hasMany(VersementCommission::class, 'commission_vente_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Recalcule et persiste le statut de la commission selon l'état de ses versements.
     *
     *  - tous les versements actifs EFFECTUE → payee  → déclenche clôture commande
     *  - au moins un versement partiel       → partielle
     *  - aucun paiement                      → impayee (inchangé)
     */
    public function recalculStatut(): void
    {
        $versements = $this->versements()
            ->whereNotIn('statut', [StatutVersementCommission::ANNULE->value])
            ->get();

        if ($versements->isEmpty()) {
            return;
        }

        $tousEffectues     = $versements->every(
            fn ($v) => $v->statut === StatutVersementCommission::EFFECTUE
        );
        $auMoinsUnPaiement = $versements->some(
            fn ($v) => in_array($v->statut->value ?? $v->statut, ['effectue', 'partiellement_verse'])
        );

        if ($tousEffectues) {
            $this->update(['statut' => StatutCommissionVente::PAYEE->value]);
            $this->commande()->withoutGlobalScopes()->first()?->cloturerSiComplete();
        } elseif ($auMoinsUnPaiement) {
            $this->update(['statut' => StatutCommissionVente::PARTIELLE->value]);
        }
        // sinon : impayee → on ne régresse pas
    }
}
