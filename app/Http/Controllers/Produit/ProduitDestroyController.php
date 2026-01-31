<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProduitDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke($id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            // Supprimer l'image si elle existe
            if ($produit->image_url) {
                $path = str_replace(url('storage') . '/', '', $produit->image_url);
                Storage::disk('public')->delete($path);
            }

            // Soft delete (conserve l'historique)
            $produit->delete();

            Log::info('Produit supprimé (soft delete)', ['produit_id' => $id]);

            return $this->successResponse(null, 'Produit supprimé avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du produit', [
                'produit_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la suppression du produit', $e->getMessage());
        }
    }
}
