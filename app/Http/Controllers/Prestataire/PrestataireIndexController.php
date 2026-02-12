<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Prestataire;
use Illuminate\Http\Request;

class PrestataireIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $query = Prestataire::query();

            // Filtre par statut actif
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filtre par spécialité
            if ($request->has('specialite')) {
                $query->parSpecialite($request->specialite);
            }

            // Recherche globale
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('prenom', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('raison_sociale', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%");
                });
            }

            // Tri
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSorts = ['nom', 'prenom', 'created_at', 'updated_at', 'specialite', 'tarif_horaire'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            if ($request->has('per_page')) {
                $prestataires = $query->paginate((int) $request->per_page);
            } else {
                $prestataires = $query->get();
            }

            return $this->successResponse($prestataires, 'Liste des prestataires récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des prestataires', $e->getMessage());
        }
    }
}
