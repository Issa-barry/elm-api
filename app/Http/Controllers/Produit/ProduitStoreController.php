<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produit\StoreProduitRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Models\ProduitUsine;
use App\Models\Stock;
use App\Models\Usine;
use App\Services\UsineContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProduitStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreProduitRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();

                // Extraire les données stock et les affectations usines
                $stockQte    = (int) ($data['qte_stock'] ?? 0);
                $stockSeuil  = isset($data['seuil_alerte_stock']) ? (int) $data['seuil_alerte_stock'] : null;
                $affectations = $data['usines'] ?? [];
                unset($data['qte_stock'], $data['seuil_alerte_stock'], $data['usines']);

                // Générer code si non fourni
                if (empty($data['code'])) {
                    $data['code'] = $this->generateNumericProductCode();
                }

                // Statut par défaut selon le stock et le type
                if (empty($data['statut'])) {
                    $type = ProduitType::from($data['type']);

                    if ($type === ProduitType::SERVICE || $stockQte > 0) {
                        $data['statut'] = ProduitStatut::ACTIF->value;
                    } else {
                        $data['statut'] = ProduitStatut::BROUILLON->value;
                    }
                }

                $produit = Produit::create($data);

                // ── Stock + config locale ────────────────────────────────────────
                if ($produit->type !== ProduitType::SERVICE) {
                    if ($produit->is_global) {
                        // Produit global : créer stock + config locale pour toutes les usines
                        Usine::withoutGlobalScopes()->get()
                            ->each(function (Usine $usine) use ($produit, $stockQte, $stockSeuil) {
                                Stock::firstOrCreate(
                                    ['produit_id' => $produit->id, 'usine_id' => $usine->id],
                                    ['qte_stock' => $stockQte, 'seuil_alerte_stock' => $stockSeuil]
                                );
                                ProduitUsine::firstOrCreate(
                                    ['produit_id' => $produit->id, 'usine_id' => $usine->id],
                                    ['is_active' => false]
                                );
                            });
                    } else {
                        // Produit non-global : stock + config locale pour l'usine courante
                        $usineId = app(UsineContext::class)->getCurrentUsineId();
                        if ($usineId) {
                            Stock::create([
                                'produit_id'         => $produit->id,
                                'usine_id'           => $usineId,
                                'qte_stock'          => $stockQte,
                                'seuil_alerte_stock' => $stockSeuil,
                            ]);
                            ProduitUsine::firstOrCreate(
                                ['produit_id' => $produit->id, 'usine_id' => $usineId],
                                ['is_active' => false]
                            );
                        }
                    }
                }

                // ── Affectations initiales explicites (usines[]) ─────────────────
                foreach ($affectations as $affectation) {
                    $usineId = (int) $affectation['usine_id'];

                    $config = ProduitUsine::firstOrCreate(
                        ['produit_id' => $produit->id, 'usine_id' => $usineId],
                        ['is_active' => false]
                    );

                    // Appliquer les prix locaux si fournis
                    $config->fill(array_filter([
                        'is_active'  => $affectation['is_active']  ?? null,
                        'prix_usine' => $affectation['prix_usine'] ?? null,
                        'prix_achat' => $affectation['prix_achat'] ?? null,
                        'prix_vente' => $affectation['prix_vente'] ?? null,
                        'cout'       => $affectation['cout']       ?? null,
                        'tva'        => $affectation['tva']        ?? null,
                    ], fn ($v) => $v !== null))->save();

                    // Garantir l'entrée stock si le produit est stockable
                    if ($produit->type !== ProduitType::SERVICE) {
                        Stock::firstOrCreate(
                            ['produit_id' => $produit->id, 'usine_id' => $usineId],
                            ['qte_stock' => 0]
                        );
                    }
                }

                // ── Upload image ─────────────────────────────────────────────────
                if ($request->hasFile('image')) {
                    $path = $request->file('image')->store("produits/{$produit->id}", 'public');
                    $produit->update(['image_url' => Storage::disk('public')->url($path)]);
                }

                $produit->load(['creator:id,nom,prenom', 'stockCourant', 'produitUsineCourant']);

                return $this->createdResponse($produit, 'Produit créé avec succès');
            });
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
