<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Illuminate\Http\Request;

class PackingIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $query = Packing::with('prestataire');

            // Filtre par prestataire
            if ($request->has('prestataire_id')) {
                $query->parPrestataire($request->integer('prestataire_id'));
            }

            // Filtre par statut
            if ($request->has('statut')) {
                $query->parStatut($request->statut);
            }

            // Filtre par date (plage)
            if ($request->has('date_debut') && $request->has('date_fin')) {
                $query->parPeriode($request->date_debut, $request->date_fin);
            } elseif ($request->has('date_debut')) {
                $query->where('date', '>=', $request->date_debut);
            } elseif ($request->has('date_fin')) {
                $query->where('date', '<=', $request->date_fin);
            }

            // Filtre non payés
            if ($request->boolean('non_payes')) {
                $query->nonPayes();
            }

            // Recherche globale
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhereHas('prestataire', function ($sub) use ($search) {
                          $sub->where('nom', 'like', "%{$search}%")
                              ->orWhere('prenom', 'like', "%{$search}%")
                              ->orWhere('phone', 'like', "%{$search}%");
                      });
                });
            }

            // Tri
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSorts = ['date', 'created_at', 'montant', 'nb_rouleaux', 'statut'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            if ($request->has('per_page')) {
                $packings = $query->paginate((int) $request->per_page);
            } else {
                $packings = $query->get();
            }

            return $this->successResponse($packings, 'Liste des packings récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des packings', $e->getMessage());
        }
    }
}
