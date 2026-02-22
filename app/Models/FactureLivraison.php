<?php

namespace App\Models;

use App\Enums\StatutFactureLivraison;
use App\Models\Traits\HasUsineScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FactureLivraison extends Model
{
    use HasFactory, SoftDeletes, HasUsineScope;

    protected $table = 'factures_livraisons';

    protected $fillable = [
        'usine_id',
        'sortie_vehicule_id',
        'vehicule_id',
        'packs_charges',
        'snapshot_mode_commission',
        'snapshot_valeur_commission',
        'snapshot_pourcentage_proprietaire',
        'snapshot_pourcentage_livreur',
        'reference',
        'montant_brut',
        'montant_net',
        'statut_facture',
    ];

    protected function casts(): array
    {
        return [
            'montant_brut'                      => 'decimal:2',
            'montant_net'                       => 'decimal:2',
            'snapshot_valeur_commission'        => 'decimal:2',
            'snapshot_pourcentage_proprietaire' => 'decimal:2',
            'snapshot_pourcentage_livreur'      => 'decimal:2',
            'packs_charges'                     => 'integer',
            'statut_facture'                    => StatutFactureLivraison::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FactureLivraison $facture) {
            if (empty($facture->reference)) {
                $lastId = self::withTrashed()->max('id') ?? 0;
                $facture->reference = 'FAC-LIV-' . now()->format('Ymd') . '-' . str_pad(
                    $lastId + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }
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

    public function sortieVehicule(): BelongsTo
    {
        return $this->belongsTo(SortieVehicule::class);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function usine(): BelongsTo
    {
        return $this->belongsTo(Usine::class);
    }

    public function encaissements(): HasMany
    {
        return $this->hasMany(EncaissementLivraison::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(DeductionCommission::class, 'facture_livraison_id');
    }

    public function paiementCommission(): HasOne
    {
        return $this->hasOne(PaiementCommission::class, 'facture_livraison_id');
    }

    public function recalculStatut(): void
    {
        $encaisse = $this->encaissements()->sum('montant');
        $net      = (float) $this->montant_net;

        if ($encaisse <= 0) {
            $statut = StatutFactureLivraison::EMISE;
        } elseif ($encaisse >= $net) {
            $statut = StatutFactureLivraison::PAYEE;
        } else {
            $statut = StatutFactureLivraison::PARTIELLEMENT_PAYEE;
        }

        $this->update(['statut_facture' => $statut]);
    }
}
