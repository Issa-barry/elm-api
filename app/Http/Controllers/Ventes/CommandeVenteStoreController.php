<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutFactureVente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vente\StoreCommandeVenteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CommandeVente;
use App\Models\FactureVente;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;

class CommandeVenteStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreCommandeVenteRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            // 1. Préparer les lignes avec snapshots de prix
            $lignesData = [];
            $totalCommande = 0;

            foreach ($validated['lignes'] as $ligne) {
                $produit = Produit::withoutGlobalScopes()->find($ligne['produit_id']);
                $qte     = (int) $ligne['qte'];
                $prixUsine  = (int) $produit->prix_usine;
                $prixVente  = (int) $produit->prix_vente;
                $totalLigne = $prixVente * $qte;

                $lignesData[] = [
                    'produit_id'          => $produit->id,
                    'qte'                 => $qte,
                    'prix_usine_snapshot' => $prixUsine,
                    'prix_vente_snapshot' => $prixVente,
                    'total_ligne'         => $totalLigne,
                ];

                $totalCommande += $totalLigne;
            }

            // 2. Créer la commande
            $commande = CommandeVente::create([
                'vehicule_id'    => $validated['vehicule_id'],
                'total_commande' => $totalCommande,
            ]);

            // 3. Créer les lignes
            foreach ($lignesData as $ligneData) {
                $commande->lignes()->create($ligneData);
            }

            // 4. Créer automatiquement la facture liée
            FactureVente::create([
                'vehicule_id'       => $commande->vehicule_id,
                'commande_vente_id' => $commande->id,
                'montant_brut'      => $totalCommande,
                'montant_net'       => $totalCommande,
                'statut_facture'    => StatutFactureVente::IMPAYEE->value,
            ]);

            $commande->load(['vehicule', 'lignes.produit', 'facture']);

            return $this->createdResponse($commande, 'Commande créée avec succès');
        });
    }
}
