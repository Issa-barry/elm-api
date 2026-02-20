<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produit\StoreProduitRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;

class ProduitStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreProduitRequest $request)
    {
        try {
            $data = $request->validated();

            // Générer code si non fourni
            if (empty($data['code'])) {
                $data['code'] = $this->generateNumericProductCode();
            }

            // Statut par défaut selon le stock et le type
            if (empty($data['statut'])) {
                $type = ProduitType::from($data['type']);
                $qteStock = $data['qte_stock'] ?? 0;

                if ($type === ProduitType::SERVICE || $qteStock > 0) {
                    $data['statut'] = ProduitStatut::ACTIF->value;
                } else {
                    $data['statut'] = ProduitStatut::BROUILLON->value;
                }
            }

            $produit = Produit::create($data);

            $produit->load(['creator:id,nom,prenom']);

            return $this->createdResponse($produit, 'Produit créé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création du produit: ' . $e->getMessage());
        }
    }

    /**
     * Format 100% numérique (12 chiffres):
     * AAAAMMJJ + NNNN
     * Exemple: 202602120001
     */
    private function generateNumericProductCode(): string
    {
        $prefix = now()->format('Ymd');

        $lastCode = Produit::withTrashed()
            ->where('code', 'like', $prefix . '%')
            ->whereRaw('LENGTH(code) = 12')
            ->orderByDesc('code')
            ->value('code');

        $nextSequence = 1;
        if ($lastCode) {
            $nextSequence = ((int) substr($lastCode, -4)) + 1;
        }

        $nextSequence = min($nextSequence, 9999);

        return $prefix . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
