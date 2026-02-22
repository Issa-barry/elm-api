<?php

namespace App\Models;

use App\Enums\StatutPaiementCommission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementCommission extends Model
{
    use HasFactory;

    protected $table = 'paiements_commissions';

    protected $fillable = [
        'sortie_vehicule_id',
        'facture_livraison_id',
        'commission_brute_totale',
        'part_proprietaire_brute',
        'part_livreur_brute',
        'part_proprietaire_nette',
        'part_livreur_nette',
        'date_paiement',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'commission_brute_totale'  => 'decimal:2',
            'part_proprietaire_brute'  => 'decimal:2',
            'part_livreur_brute'       => 'decimal:2',
            'part_proprietaire_nette'  => 'decimal:2',
            'part_livreur_nette'       => 'decimal:2',
            'date_paiement'            => 'date',
            'statut'                   => StatutPaiementCommission::class,
        ];
    }

    public function sortie(): BelongsTo
    {
        return $this->belongsTo(SortieVehicule::class, 'sortie_vehicule_id');
    }

    public function factureLivraison(): BelongsTo
    {
        return $this->belongsTo(FactureLivraison::class, 'facture_livraison_id');
    }
}
