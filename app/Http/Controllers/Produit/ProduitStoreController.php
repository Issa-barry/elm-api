<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produit\StoreProduitRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Models\ProduitSite;
use App\Models\Stock;
use App\Models\Site;
use App\Services\SiteContext;
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

                // Extraire le seuil d'alerte et les affectations ; qte_stock ignoré (toujours 0 à la création)
                $stockSeuil   = isset($data['seuil_alerte_stock']) ? (int) $data['seuil_alerte_stock'] : null;
                $affectations = $data['usines'] ?? [];
                unset($data['qte_stock'], $data['seuil_alerte_stock'], $data['usines']);

                // Générer code si non fourni
                if (empty($data['code'])) {
                    $data['code'] = $this->generateNumericProductCode();
                }

                // Auto-générer code_interne depuis code si absent
                if (empty($data['code_interne'])) {
                    $data['code_interne'] = $data['code'];
                }

                // Statut par défaut : BROUILLON — activation explicite requise
                if (empty($data['statut'])) {
                    $data['statut'] = ProduitStatut::BROUILLON->value;
                }

                $produit = Produit::create($data);

                // ── Config locale (produit_usines) + Stock (non-services uniquement) ──
                // Tous les produits démarrent avec is_active = false dans chaque usine.
                // Le stock initial est 0 ; il sera saisi lors de l'activation par usine.
                if ($produit->is_global) {
                    Site::withoutGlobalScopes()->get()
                        ->each(function (Site $site) use ($produit, $stockSeuil) {
                            ProduitSite::firstOrCreate(
                                ['produit_id' => $produit->id, 'site_id' => $site->id],
                                ['is_active' => false]
                            );
                            if ($produit->type !== ProduitType::SERVICE) {
                                Stock::firstOrCreate(
                                    ['produit_id' => $produit->id, 'site_id' => $site->id],
                                    ['qte_stock' => 0, 'seuil_alerte_stock' => $stockSeuil]
                                );
                            }
                        });
                } else {
                    $siteId = app(SiteContext::class)->getCurrentSiteId();
                    if ($siteId) {
                        ProduitSite::firstOrCreate(
                            ['produit_id' => $produit->id, 'site_id' => $siteId],
                            ['is_active' => false]
                        );
                        if ($produit->type !== ProduitType::SERVICE) {
                            Stock::create([
                                'produit_id'         => $produit->id,
                                'site_id'           => $siteId,
                                'qte_stock'          => 0,
                                'seuil_alerte_stock' => $stockSeuil,
                            ]);
                        }
                    }
                }

                // ── Affectations initiales explicites (usines[]) ─────────────────
                // is_active toujours false à la création ; les prix locaux sont acceptés.
                foreach ($affectations as $affectation) {
                    $siteId = (int) $affectation['site_id'];

                    $config = ProduitSite::firstOrCreate(
                        ['produit_id' => $produit->id, 'site_id' => $siteId],
                        ['is_active' => false]
                    );

                    // Appliquer les prix locaux si fournis (is_active ignoré)
                    $config->fill(array_filter([
                        'prix_usine' => $affectation['prix_usine'] ?? null,
                        'prix_achat' => $affectation['prix_achat'] ?? null,
                        'prix_vente' => $affectation['prix_vente'] ?? null,
                        'cout'       => $affectation['cout']       ?? null,
                        'tva'        => $affectation['tva']        ?? null,
                    ], fn ($v) => $v !== null))->save();

                    if ($produit->type !== ProduitType::SERVICE) {
                        Stock::firstOrCreate(
                            ['produit_id' => $produit->id, 'site_id' => $siteId],
                            ['qte_stock' => 0]
                        );
                    }
                }

                // ── Upload image ─────────────────────────────────────────────────
                if ($request->hasFile('image')) {
                    Storage::disk('public')->deleteDirectory("produits/{$produit->id}");
                    $path = $request->file('image')->store("produits/{$produit->id}", 'public');
                    $produit->update(['image_url' => Storage::disk('public')->url($path)]);
                }

                $produit->load(['creator:id,nom,prenom', 'stockCourant', 'produitSiteCourant']);

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
