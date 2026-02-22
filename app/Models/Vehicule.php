<?php

namespace App\Models;

use App\Enums\ModeCommission;
use App\Enums\TypeVehicule;
use App\Models\Traits\HasUsineScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Vehicule extends Model
{
    use HasFactory, SoftDeletes, HasUsineScope;

    protected $fillable = [
        'usine_id',
        'nom_vehicule',
        'marque',
        'modele',
        'immatriculation',
        'type_vehicule',
        'capacite_packs',
        'proprietaire_id',
        'livreur_principal_id',
        'pris_en_charge_par_usine',
        'mode_commission',
        'valeur_commission',
        'pourcentage_proprietaire',
        'pourcentage_livreur',
        'photo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'               => 'boolean',
            'pris_en_charge_par_usine' => 'boolean',
            'type_vehicule'           => TypeVehicule::class,
            'mode_commission'         => ModeCommission::class,
            'capacite_packs'          => 'integer',
            'valeur_commission'       => 'decimal:2',
            'pourcentage_proprietaire' => 'decimal:2',
            'pourcentage_livreur'     => 'decimal:2',
        ];
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    protected $appends = ['photo_url'];

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Proprietaire::class);
    }

    public function livreurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Livreur::class, 'livreur_principal_id');
    }

    public function usine(): BelongsTo
    {
        return $this->belongsTo(Usine::class);
    }

    public function sorties(): HasMany
    {
        return $this->hasMany(SortieVehicule::class);
    }

    public function sortieEnCours(): HasOne
    {
        return $this->hasOne(SortieVehicule::class)->where('statut_sortie', 'en_cours');
    }
}
