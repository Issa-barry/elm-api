<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutFactureVente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vente\StoreCommandeVenteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CommandeVente;
use Illuminate\Validation\ValidationException;
use App\Models\FactureVente;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Vehicule;
use App\Services\SiteContext;
use Illuminate\Support\Facades\DB;

class CommandeVenteStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreCommandeVenteRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
            $validated  = $request->validated();
            $siteId    = app(SiteContext::class)->getCurrentSiteId();

            // 1. Préparer les lignes avec snapshots de prix
            $lignesData   = [];
            $stocksQtes   = []; // [stock => qte] pour décrémenter après création commande
            $totalCommande = 0;

            foreach ($validated['lignes'] as $ligne) {
                // Lire les infos produit (pas besoin de lockForUpdate sur le produit)
                $produit    = Produit::withoutGlobalScopes()->find($ligne['produit_id']);
                $qte        = (int) $ligne['qte'];
                $prixUsine  = (int) $produit->prix_usine;
                $prixVente  = (int) $produit->prix_vente;
                $totalLigne = $prixVente * $qte;

                // lockForUpdate sur le stock pour les opérations concurrentes
                $stock = Stock::where('produit_id', $produit->id)
                    ->where('site_id', $siteId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Vérifier que le stock est suffisant
                if ($stock->qte_stock < $qte) {
                    throw ValidationException::withMessages([
                        'lignes' => "Stock insuffisant pour \"{$produit->nom}\" — disponible : {$stock->qte_stock}, demandé : {$qte}.",
                    ]);
                }

                $lignesData[] = [
                    'produit_id'          => $produit->id,
                    'qte'                 => $qte,
                    'prix_usine_snapshot' => $prixUsine,
                    'prix_vente_snapshot' => $prixVente,
                    'total_ligne'         => $totalLigne,
                ];

                $stocksQtes[]  = ['stock' => $stock, 'qte' => $qte];
                $totalCommande += $totalLigne;
            }

            // 2. Créer la commande
            $commande = CommandeVente::create([
                'vehicule_id'    => $validated['vehicule_id'],
                'total_commande' => $totalCommande,
                'created_by'     => auth()->id(),
            ]);

            // 3. Créer les lignes
            foreach ($lignesData as $ligneData) {
                $commande->lignes()->create($ligneData);
            }

            // 3b. Décrémenter le stock de chaque produit
            foreach ($stocksQtes as ['stock' => $stock, 'qte' => $qte]) {
                $stock->ajuster(-$qte);
            }

            // 4. Créer automatiquement la facture liée
            FactureVente::create([
                'vehicule_id'       => $commande->vehicule_id,
                'commande_vente_id' => $commande->id,
                'montant_brut'      => $totalCommande,
                'montant_net'       => $totalCommande,
                'statut_facture'    => StatutFactureVente::IMPAYEE->value,
            ]);

            // La commission est créée plus tard, uniquement quand la facture passe à "payee"
            // (voir CommissionVenteService::creerSiEligible, appelé par EncaissementVenteStoreController)

            $commande->load(['vehicule', 'lignes.produit', 'facture', 'commission.versements']);

            return $this->createdResponse($commande, 'Commande créée avec succès');
            });
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Impossible de créer la commande.');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création de la commande', $e->getMessage());
        }
    }
}
