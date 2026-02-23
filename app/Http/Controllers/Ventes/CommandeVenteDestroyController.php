<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommandeVente;

class CommandeVenteDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $commande = CommandeVente::with('facture.encaissements')->find($id);

        if (! $commande) {
            return $this->notFoundResponse('Commande introuvable.');
        }

        if ($commande->facture && $commande->facture->encaissements()->exists()) {
            return $this->errorResponse(
                'Impossible de supprimer une commande ayant des encaissements.',
                null,
                422
            );
        }

        if ($commande->facture) {
            $commande->facture->delete();
        }

        $commande->delete();

        return $this->successResponse(null, 'Commande supprimée avec succès.');
    }
}
