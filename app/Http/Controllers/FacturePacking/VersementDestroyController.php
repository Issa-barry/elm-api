<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FacturePacking;
use App\Models\Versement;
use Illuminate\Support\Facades\DB;

class VersementDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $factureId, int $versementId)
    {
        try {
            return DB::transaction(function () use ($factureId, $versementId) {
                $facture = FacturePacking::findOrFail($factureId);
                $versement = Versement::where('facture_packing_id', $factureId)
                    ->where('id', $versementId)
                    ->firstOrFail();

                $versement->delete();

                // Mettre à jour le statut de la facture
                $facture->mettreAJourStatut();

                // Recharger la facture avec les versements
                $facture->load(['prestataire', 'packings', 'versements']);

                return $this->successResponse([
                    'facture' => $facture,
                ], 'Versement supprimé avec succès');
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Facture ou versement non trouvé');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du versement', $e->getMessage());
        }
    }
}
