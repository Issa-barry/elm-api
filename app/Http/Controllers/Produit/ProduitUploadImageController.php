<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProduitUploadImageController extends Controller
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
                'image' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                    function ($attribute, $value, $fail) {
                        $parts = explode('.', $value->getClientOriginalName());
                        if (count($parts) > 2) {
                            $fail('Nom de fichier invalide.');
                        }
                    },
                ],
            ], [
                'image.required' => 'L\'image est obligatoire.',
                'image.image'    => 'Le fichier doit être une image.',
                'image.mimes'    => 'Formats acceptés : jpg, jpeg, png, webp.',
                'image.max'      => 'L\'image ne doit pas dépasser 5 Mo.',
            ]);

            // Supprimer l'ancienne image
            if ($produit->image_url) {
                $oldPath = str_replace(Storage::disk('public')->url(''), '', $produit->image_url);
                Storage::disk('public')->delete(ltrim($oldPath, '/'));
            }

            // Stocker sans compression (intervention/image non installé)
            $path = $request->file('image')->store("produits/{$produit->id}", 'public');
            $url  = Storage::disk('public')->url($path);

            $produit->update(['image_url' => $url]);
            $produit->load(['creator:id,nom,prenom', 'updater:id,nom,prenom']);

            return $this->successResponse($produit, 'Image uploadée avec succès');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('Erreur upload image produit', [
                'produit_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return $this->errorResponse('Erreur lors de l\'upload de l\'image');
        }
    }
}
