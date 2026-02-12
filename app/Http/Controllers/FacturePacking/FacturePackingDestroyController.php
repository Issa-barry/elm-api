<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FacturePacking;
use Illuminate\Support\Facades\DB;

class FacturePackingDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $facture = FacturePacking::findOrFail($id);

                // Vérifier si la facture a des versements
                if ($facture->versements()->exists()) {
                    return $this->errorResponse(
                        'Impossible de supprimer une facture avec des versements. Annulez-la à la place.',
                        null,
                        422
                    );
                }

                // Supprimer la facture (libère les packings)
                $facture->supprimer();

                return $this->successResponse(null, 'Facture supprimée avec succès');
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Facture non trouvée');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression de la facture', $e->getMessage());
        }
    }
}
