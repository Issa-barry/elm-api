<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommandeVente;

class CommandeVenteShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $commande = CommandeVente::with([
            'vehicule.proprietaire',
            'vehicule.livreurPrincipal',
            'lignes.produit',
            'facture.encaissements',
        ])->find($id);

        if (! $commande) {
            return $this->notFoundResponse('Commande non trouvée');
        }

        return $this->successResponse($commande, 'Détail de la commande de vente');
    }
}
