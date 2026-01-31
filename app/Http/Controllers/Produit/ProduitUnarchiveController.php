<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProduitUnarchiveController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, $id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            if ($produit->statut !== ProduitStatut::ARCHIVE) {
                return $this->errorResponse('Le produit n\'est pas archivé', null, 400);
            }

            // Statut cible (par défaut: inactif)
            $request->validate([
                'statut' => ['nullable', Rule::in([
                    ProduitStatut::ACTIF->value,
                    ProduitStatut::INACTIF->value,
                ])],
            ], [
                'statut.in' => 'Le statut de destination doit être : actif ou inactif',
            ]);

            $nouveauStatut = ProduitStatut::tryFrom($request->statut) ?? ProduitStatut::INACTIF;

            $produit->desarchiver($nouveauStatut);

            Log::info('Produit désarchivé', [
                'produit_id' => $produit->id,
                'nouveau_statut' => $nouveauStatut->value
            ]);

            return $this->successResponse($produit->fresh(), 'Produit désarchivé avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la désarchivation du produit', [
                'produit_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la désarchivation du produit', $e->getMessage());
        }
    }
}
