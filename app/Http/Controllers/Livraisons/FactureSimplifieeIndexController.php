<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureLivraison;

/**
 * Liste des factures du workflow simplifié (liées à un véhicule, sans sortie).
 */
class FactureSimplifieeIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        $factures = FactureLivraison::with(['vehicule.proprietaire', 'vehicule.livreurPrincipal', 'encaissements'])
            ->whereNotNull('vehicule_id')
            ->whereNull('sortie_vehicule_id')
            ->when(request('statut'), fn ($q, $s) => $q->where('statut_facture', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successResponse($factures, 'Liste des factures');
    }
}
