<?php

namespace App\Http\Controllers\PaiementPacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PaiementPacking;

class VersementIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(int $paiementId)
    {
        try {
            $paiement = PaiementPacking::with(['versements.creator', 'prestataire'])
                ->findOrFail($paiementId);

            $data = [
                'paiement_id' => $paiement->id,
                'reference' => $paiement->reference,
                'prestataire_nom' => $paiement->prestataire_nom,
                'montant_total' => $paiement->montant_total,
                'montant_verse' => $paiement->montant_verse,
                'montant_restant' => $paiement->montant_restant,
                'is_solde' => $paiement->is_solde,
                'versements' => $paiement->versements,
            ];

            return $this->successResponse($data, 'Liste des versements récupérée avec succès');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Paiement non trouvé');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des versements', $e->getMessage());
        }
    }
}
