<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutFactureVente;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureVente;

/**
 * Annule une facture de vente.
 *
 * Règles :
 *  - Impossible d'annuler si la facture est déjà payée (statut = payee)
 *  - Une fois annulée, aucun encaissement n'est possible
 */
class FactureVenteAnnulerController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $facture = FactureVente::find($id);

        if (!$facture) {
            return $this->notFoundResponse('Facture non trouvée');
        }

        if ($facture->statut_facture === StatutFactureVente::ANNULEE) {
            return $this->errorResponse('La facture est déjà annulée.', null, 409);
        }

        if ($facture->statut_facture === StatutFactureVente::PAYEE) {
            return $this->errorResponse(
                'Impossible d\'annuler une facture entièrement payée.',
                null,
                422
            );
        }

        $facture->update(['statut_facture' => StatutFactureVente::ANNULEE]);

        return $this->successResponse($facture->fresh(), 'Facture annulée avec succès');
    }
}
