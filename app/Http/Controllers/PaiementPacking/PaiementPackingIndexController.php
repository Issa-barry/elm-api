<?php

namespace App\Http\Controllers\PaiementPacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PaiementPacking;
use Illuminate\Http\Request;

class PaiementPackingIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $query = PaiementPacking::with(['prestataire', 'packings', 'versements']);

            // Filtre par prestataire
            if ($request->has('prestataire_id')) {
                $query->parPrestataire($request->integer('prestataire_id'));
            }

            // Filtre par période
            if ($request->has('periode_debut') && $request->has('periode_fin')) {
                $query->parPeriode($request->periode_debut, $request->periode_fin);
            }

            // Filtre par date de paiement
            if ($request->has('date_paiement_debut') && $request->has('date_paiement_fin')) {
                $query->whereBetween('date_paiement', [
                    $request->date_paiement_debut,
                    $request->date_paiement_fin,
                ]);
            }

            // Filtre par mode de paiement
            if ($request->has('mode_paiement')) {
                $query->where('mode_paiement', $request->mode_paiement);
            }

            // Filtre par statut de solde
            if ($request->has('solde')) {
                if ($request->boolean('solde')) {
                    $query->soldes();
                } else {
                    $query->nonSoldes();
                }
            }

            // Recherche globale
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhereHas('prestataire', function ($sub) use ($search) {
                          $sub->where('nom', 'like', "%{$search}%")
                              ->orWhere('prenom', 'like', "%{$search}%")
                              ->orWhere('raison_sociale', 'like', "%{$search}%");
                      });
                });
            }

            // Tri
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSorts = ['date_paiement', 'created_at', 'montant_total', 'nb_packings'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            if ($request->has('per_page')) {
                $paiements = $query->paginate((int) $request->per_page);
            } else {
                $paiements = $query->get();
            }

            return $this->successResponse($paiements, 'Liste des paiements récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des paiements', $e->getMessage());
        }
    }
}
