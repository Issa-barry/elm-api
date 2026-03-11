<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommissionVente;

class CommissionVenteShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $commission = CommissionVente::with([
            'commande.lignes.produit',
            'vehicule',
            'livreur',
            'proprietaire',
            'versements.paiements.versePar:id,nom,prenom',
        ])->find($id);

        if (!$commission) {
            return $this->notFoundResponse('Commission non trouvée');
        }

        return $this->successResponse($commission, 'Détail de la commission de vente');
    }
}
