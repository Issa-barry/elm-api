<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FacturePacking;
use Illuminate\Http\Request;

class FacturePackingIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $query = FacturePacking::with(['prestataire', 'packings', 'versements']);

            // Filtre par prestataire
            if ($request->has('prestataire_id')) {
                $query->parPrestataire($request->integer('prestataire_id'));
            }

            // Filtre par période
            if ($request->has('periode_debut') && $request->has('periode_fin')) {
                $query->parPeriode($request->periode_debut, $request->periode_fin);
            }

            // Filtre par statut
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            // Filtre par statut de solde
            if ($request->has('soldee')) {
                if ($request->boolean('soldee')) {
                    $query->soldees();
                } else {
                    $query->nonSoldees();
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
            $allowedSorts = ['created_at', 'montant_total', 'nb_packings', 'periode_debut'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            if ($request->has('per_page')) {
                $factures = $query->paginate((int) $request->per_page);
            } else {
                $factures = $query->get();
            }

            return $this->successResponse($factures, 'Liste des factures récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des factures', $e->getMessage());
        }
    }
}
