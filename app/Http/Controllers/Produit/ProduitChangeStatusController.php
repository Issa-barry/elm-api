<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProduitChangeStatusController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, $id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            $request->validate([
                'statut' => ['required', Rule::enum(ProduitStatut::class)],
            ], [
                'statut.required' => 'Le statut est obligatoire.',
                'statut.Illuminate\Validation\Rules\Enum' => 'Le statut doit être : brouillon, actif, inactif, archive ou rupture_stock.',
            ]);

            $nouveauStatut = ProduitStatut::from($request->statut);
            $ancienStatut = $produit->statut;

            // Vérifier la transition
            if (!$ancienStatut->canTransitionTo($nouveauStatut)) {
                return $this->errorResponse(
                    "Transition de '{$ancienStatut->label()}' vers '{$nouveauStatut->label()}' non autorisée.",
                    [
                        'transitions_autorisees' => array_map(
                            fn($s) => $s->value,
                            $ancienStatut->allowedTransitions()
                        )
                    ],
                    400
                );
            }

            // Vérifier cohérence stock/statut
            if ($nouveauStatut === ProduitStatut::ACTIF
                && $produit->qte_stock <= 0
                && $produit->type !== ProduitType::SERVICE) {
                return $this->errorResponse(
                    'Impossible de passer en actif : le stock est à zéro. Le produit sera en rupture de stock.',
                    null,
                    400
                );
            }

            $produit->changerStatut($nouveauStatut);

            return $this->successResponse([
                'produit' => $produit->fresh(),
                'ancien_statut' => $ancienStatut->value,
                'nouveau_statut' => $produit->statut->value,
            ], 'Statut mis à jour avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du statut', $e->getMessage());
        }
    }
}
