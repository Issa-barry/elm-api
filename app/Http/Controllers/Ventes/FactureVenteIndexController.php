<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureVente;

class FactureVenteIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        $factures = FactureVente::with(['vehicule.proprietaire', 'vehicule.livreurPrincipal', 'encaissements'])
            ->when(request('statut'), fn ($q, $s) => $q->where('statut_facture', $s))
            ->when(request('vehicule_id'), fn ($q, $v) => $q->where('vehicule_id', $v))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successResponse($factures, 'Liste des factures de vente');
    }
}
