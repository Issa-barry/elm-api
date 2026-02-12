<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Support\Facades\Log;

class ProduitArchiveController extends Controller
{
    use ApiResponse;

    public function __invoke($id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            if ($produit->statut === ProduitStatut::ARCHIVE) {
                return $this->errorResponse('Le produit est déjà archivé', null, 400);
            }

            if (!$produit->statut->canTransitionTo(ProduitStatut::ARCHIVE)) {
                return $this->errorResponse(
                    "Impossible d'archiver un produit avec le statut '{$produit->statut->label()}'",
                    null,
                    400
                );
            }

            $ancienStatut = $produit->statut;
            $produit->archiver();

            Log::info('Produit archivé', ['produit_id' => $produit->id]);

            return $this->successResponse([
                'produit' => $produit->fresh(),
                'ancien_statut' => $ancienStatut->value,
            ], 'Produit archivé avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'archivage du produit', [
                'produit_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de l\'archivage du produit', $e->getMessage());
        }
    }
}
