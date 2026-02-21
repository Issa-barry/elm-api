<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProduitDeleteImageController extends Controller
{
    use ApiResponse;

    public function __invoke($id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            if (!$produit->image_url) {
                return $this->errorResponse('Ce produit n\'a pas d\'image', null, 404);
            }

            $path = str_replace(Storage::disk('public')->url(''), '', $produit->image_url);
            Storage::disk('public')->delete(ltrim($path, '/'));

            $produit->update(['image_url' => null]);
            $produit->load(['creator:id,nom,prenom', 'updater:id,nom,prenom']);

            return $this->successResponse($produit, 'Image supprimée avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur suppression image produit', [
                'produit_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return $this->errorResponse('Erreur lors de la suppression de l\'image');
        }
    }
}
