<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureLivraison;
use Illuminate\Http\Request;

class FactureLivraisonIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $factures = FactureLivraison::with(['sortieVehicule.vehicule', 'encaissements'])
            ->when($request->input('statut'), fn ($q, $s) => $q->where('statut_facture', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successResponse($factures, 'Liste des factures de livraison');
    }
}
