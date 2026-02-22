<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureLivraison;

class FactureLivraisonShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $facture = FactureLivraison::with(['sortieVehicule.vehicule', 'encaissements'])->find($id);

        if (!$facture) {
            return $this->notFoundResponse('Facture de livraison non trouvée');
        }

        return $this->successResponse($facture);
    }
}
