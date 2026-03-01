<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produit\UpdateProduitRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Models\ProduitUsine;
use App\Models\Stock;
use App\Models\Usine;
use App\Services\UsineContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

            // Champs globaux (produits) sur un produit global : admins/managers uniquement.
            // Les champs locaux (prix → produit_usines, stock → stocks) sont accessibles à tous.
            $isGlobalChange = $request->has('is_global') && (bool) $request->is_global !== (bool) $produit->is_global;
            $hasGlobalFields = $request->hasAny(['nom', 'code', 'type', 'statut', 'description', 'is_critique', 'is_global'])
                || $request->hasFile('image');

            if ($isGlobalChange || ($produit->is_global && $hasGlobalFields)) {
                $user = Auth::user();
                if (!$user || !$user->hasAnyRole(['admin', 'manager'])) {
                    return $this->errorResponse(
                        'Seuls les administrateurs peuvent modifier les données globales d\'un produit.',
                        null,
                        403
                    );
                }
            }

            return DB::transaction(function () use ($request, $produit, $isGlobalChange) {
                $data = $request->validated();

                // ── Séparer les champs par destination ──────────────────────────
                // 1. Stock → table stocks (local à l'usine courante)
                $qteStock   = array_key_exists('qte_stock', $data)          ? $data['qte_stock']          : null;
                $stockSeuil = array_key_exists('seuil_alerte_stock', $data) ? $data['seuil_alerte_stock'] : null;
                unset($data['qte_stock'], $data['seuil_alerte_stock']);

                // 2. Prix → table produit_usines (local à l'usine courante)
                $localPrix = [];
                foreach (['prix_usine', 'prix_vente', 'prix_achat', 'cout'] as $key) {
                    if (array_key_exists($key, $data)) {
                        $localPrix[$key] = $data[$key];
                        unset($data[$key]);
                    }
                }

                // 3. Reste → table produits (champs globaux : nom, code, type, statut…)

                // Upload image si présente
                if ($request->hasFile('image')) {
                    Storage::disk('public')->deleteDirectory("produits/{$produit->id}");
                    $path = $request->file('image')->store("produits/{$produit->id}", 'public');
                    $data['image_url'] = Storage::disk('public')->url($path);
                }

                // ── Mise à jour des champs globaux dans produits ─────────────────
                if ($isGlobalChange) {
                    $nouvelEtat = (bool) $data['is_global'];

                    if ($nouvelEtat) {
                        // local → global : libérer usine_id, propager aux usines
                        $data['usine_id'] = null;
                        $produit->update($data);
                        $this->propagerVersToutes($produit);
                    } else {
                        // global → local : rattacher à l'usine courante
                        $usineId = app(UsineContext::class)->getCurrentUsineId();
                        if (!$usineId) {
                            throw new \RuntimeException(
                                'Impossible de rendre un produit local sans usine courante (X-Usine-Id manquant).'
                            );
                        }
                        $data['usine_id'] = $usineId;
                        $produit->update($data);
                    }
                } elseif (!empty($data)) {
                    $produit->update($data);
                }

                // ── Mise à jour des prix locaux dans produit_usines ──────────────
                // Toute modification de prix est locale à l'usine courante.
                if (!empty($localPrix)) {
                    $usineId = app(UsineContext::class)->getCurrentUsineId();
                    if ($usineId) {
                        $produitUsine = ProduitUsine::firstOrCreate(
                            ['produit_id' => $produit->id, 'usine_id' => $usineId],
                            ['is_active' => false]
                        );
                        $produitUsine->update($localPrix);
                    }
                }

                // ── Mise à jour du stock de l'usine courante dans stocks ─────────
                if ($produit->type !== ProduitType::SERVICE && ($qteStock !== null || $stockSeuil !== null)) {
                    $usineId = app(UsineContext::class)->getCurrentUsineId();
                    if ($usineId) {
                        $stock = Stock::firstOrCreate(
                            ['produit_id' => $produit->id, 'usine_id' => $usineId],
                            ['qte_stock' => 0, 'seuil_alerte_stock' => null]
                        );

                        if ($qteStock !== null) {
                            $stock->qte_stock = $qteStock;
                        }
                        if ($stockSeuil !== null) {
                            $stock->seuil_alerte_stock = $stockSeuil;
                        }
                        $stock->save();
                    }
                }

                $produit->load(['creator:id,nom,prenom', 'updater:id,nom,prenom', 'stockCourant', 'produitUsineCourant']);

                return $this->successResponse($produit, 'Produit mis à jour avec succès');
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du produit', $e->getMessage());
        }
    }

    /**
     * Quand un produit passe en global, s'assurer que chaque usine a
     * une ligne ProduitUsine (is_active=false) et une ligne Stock (qte=0).
     * Les lignes existantes sont conservées intactes.
     */
    private function propagerVersToutes(Produit $produit): void
    {
        Usine::withoutGlobalScopes()->get()
            ->each(function (Usine $usine) use ($produit) {
                ProduitUsine::firstOrCreate(
                    ['produit_id' => $produit->id, 'usine_id' => $usine->id],
                    ['is_active' => false]
                );

                if ($produit->type !== ProduitType::SERVICE) {
                    Stock::firstOrCreate(
                        ['produit_id' => $produit->id, 'usine_id' => $usine->id],
                        ['qte_stock' => 0]
                    );
                }
            });
    }
}
