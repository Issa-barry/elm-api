<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;

class ProduitShowController extends Controller
{
    use ApiResponse;

    public function __invoke($id)
    {
        try {
            $produit = Produit::with(['creator:id,nom,prenom', 'updater:id,nom,prenom', 'archivedByUser:id,nom,prenom'])
                ->find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            return $this->successResponse($produit, 'Produit récupéré avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération du produit', $e->getMessage());
        }
    }
}
