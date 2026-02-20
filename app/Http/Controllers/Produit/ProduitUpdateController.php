<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produit\UpdateProduitRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;

class ProduitUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateProduitRequest $request, $id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            $data = $request->validated();

            $produit->update($data);

            $produit->load(['creator:id,nom,prenom', 'updater:id,nom,prenom']);

            return $this->successResponse($produit, 'Produit mis à jour avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du produit', $e->getMessage());
        }
    }
}
