<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vente\UpdateCommandeVenteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CommandeVente;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;

class CommandeVenteUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateCommandeVenteRequest $request, int $id)
    {
        $commande = CommandeVente::with('facture.encaissements')->find($id);

        if (! $commande) {
            return $this->notFoundResponse('Commande introuvable.');
        }

        if ($commande->facture && $commande->facture->encaissements()->exists()) {
            return $this->errorResponse(
                'Impossible de modifier une commande ayant des encaissements.',
                null,
                422
            );
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($commande, $validated) {
            if (isset($validated['vehicule_id'])) {
                $commande->vehicule_id = $validated['vehicule_id'];
            }

            if (isset($validated['lignes'])) {
                $lignesData    = [];
                $totalCommande = 0;

                foreach ($validated['lignes'] as $ligne) {
                    $produit    = Produit::withoutGlobalScopes()->find($ligne['produit_id']);
                    $qte        = (int) $ligne['qte'];
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

                $commande->lignes()->delete();

                foreach ($lignesData as $ligneData) {
                    $commande->lignes()->create($ligneData);
                }

                $commande->total_commande = $totalCommande;

                if ($commande->facture) {
                    $commande->facture->update([
                        'montant_brut' => $totalCommande,
                        'montant_net'  => $totalCommande,
                    ]);
                }
            }

            $commande->save();
            $commande->load(['vehicule', 'lignes.produit', 'facture']);

            return $this->successResponse($commande, 'Commande mise à jour avec succès.');
        });
    }
}
