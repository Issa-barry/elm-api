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
            if ($request->filled('prestataire_id')) {
                $query->parPrestataire($request->integer('prestataire_id'));
            }

            // Filtre par date
            if ($request->filled('date_debut') && $request->filled('date_fin')) {
                $query->parDate($request->date_debut, $request->date_fin);
            } elseif ($request->filled('date_debut')) {
                $query->where('date', '>=', $request->date_debut);
            } elseif ($request->filled('date_fin')) {
                $query->where('date', '<=', $request->date_fin);
            }

            // Filtre par statut
            if ($request->filled('statut')) {
                $query->where('statut', $request->statut);
            }

            // Filtre par statut de solde
            if ($request->filled('soldee')) {
                if ($request->boolean('soldee')) {
                    $query->soldees();
                } else {
                    $query->nonSoldees();
                }
            }

            // Recherche globale
            if ($request->filled('search')) {
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
            $allowedSorts = ['created_at', 'date', 'montant_total', 'nb_packings'];
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
