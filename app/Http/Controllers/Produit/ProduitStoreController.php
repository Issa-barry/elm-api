<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produit\StoreProduitRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Support\Str;

class ProduitStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreProduitRequest $request)
    {
        try {
            $data = $request->validated();

            // Générer code si non fourni
            if (empty($data['code'])) {
                $prefix = match (ProduitType::from($data['type'])) {
                    ProduitType::MATERIEL => 'MAT',
                    ProduitType::SERVICE => 'SRV',
                    ProduitType::FABRICABLE => 'FAB',
                    ProduitType::ACHAT_VENTE => 'AV',
                };
                $data['code'] = $prefix . '-' . strtoupper(Str::random(8));
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
}
