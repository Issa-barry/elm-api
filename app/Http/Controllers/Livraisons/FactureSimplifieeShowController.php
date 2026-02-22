<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureLivraison;

class FactureSimplifieeShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $facture = FactureLivraison::with([
            'vehicule.proprietaire',
            'vehicule.livreurPrincipal',
            'encaissements',
            'deductions',
            'paiementCommission',
        ])->find($id);

        if (!$facture) {
            return $this->notFoundResponse('Facture non trouvée');
        }

        return $this->successResponse($facture, 'Détail de la facture');
    }
}
