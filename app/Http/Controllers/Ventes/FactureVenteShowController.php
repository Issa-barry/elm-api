<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureVente;

class FactureVenteShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $facture = FactureVente::with([
            'vehicule.proprietaire',
            'vehicule.livreurPrincipal',
            'encaissements',
            'commande.lignes.produit',
        ])->find($id);

        if (!$facture) {
            return $this->notFoundResponse('Facture non trouvée');
        }

        return $this->successResponse($facture, 'Détail de la facture de vente');
    }
}
