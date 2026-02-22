<?php

namespace App\Models;

use App\Enums\ModeCommission;
use App\Enums\StatutSortie;
use App\Models\Traits\HasUsineScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SortieVehicule extends Model
{
    use HasFactory, SoftDeletes, HasUsineScope;

    protected $table = 'sorties_vehicules';

    protected $fillable = [
        'usine_id',
        'vehicule_id',
        'livreur_id_effectif',
        'packs_charges',
        'packs_retour',
        'date_depart',
        'date_retour',
        'statut_sortie',
        'snapshot_mode_commission',
        'snapshot_valeur_commission',
        'snapshot_pourcentage_proprietaire',
        'snapshot_pourcentage_livreur',
    ];

    protected function casts(): array
    {
        return [
            'date_depart'                      => 'datetime',
            'date_retour'                      => 'datetime',
            'statut_sortie'                    => StatutSortie::class,
            'snapshot_mode_commission'         => ModeCommission::class,
            'snapshot_valeur_commission'       => 'decimal:2',
            'snapshot_pourcentage_proprietaire' => 'decimal:2',
            'snapshot_pourcentage_livreur'     => 'decimal:2',
            'packs_charges'                    => 'integer',
            'packs_retour'                     => 'integer',
        ];
    }

    public function getPacksLivresAttribute(): int
    {
        return $this->packs_charges - ($this->packs_retour ?? 0);
    }

    protected $appends = ['packs_livres'];

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function livreurEffectif(): BelongsTo
    {
        return $this->belongsTo(Livreur::class, 'livreur_id_effectif');
    }

    public function usine(): BelongsTo
    {
        return $this->belongsTo(Usine::class);
    }

    public function factureLivraison(): HasOne
    {
        return $this->hasOne(FactureLivraison::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(DeductionCommission::class);
    }

    public function paiementCommission(): HasOne
    {
        return $this->hasOne(PaiementCommission::class);
    }

    public function isEnCours(): bool
    {
        return $this->statut_sortie === StatutSortie::EN_COURS;
    }

    public function isRetourne(): bool
    {
        return $this->statut_sortie === StatutSortie::RETOURNE;
    }

    public function isCloture(): bool
    {
        return $this->statut_sortie === StatutSortie::CLOTURE;
    }
}
