<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;

class PackingValiderController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $packing = Packing::find($id);

            if (!$packing) {
                return $this->notFoundResponse('Packing non trouvé');
            }

            // Vérifier que le packing est en attente de validation
            if ($packing->statut !== Packing::STATUT_A_VALIDER) {
                return $this->errorResponse(
                    'Seuls les packings à valider peuvent être validés',
                    null,
                    422
                );
            }

            // Valider le packing (crée automatiquement une facture)
            $facture = $packing->valider();
            $packing->load(['prestataire', 'facture']);

            return $this->successResponse([
                'packing' => $packing,
                'facture' => $facture->load(['prestataire', 'packings']),
            ], 'Packing validé et facture créée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la validation du packing', $e->getMessage());
        }
    }
}
