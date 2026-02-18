<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;

class UserIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $query = User::with('roles');

            // Filtre par type de compte
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filtre par statut actif
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Recherche par nom, prénom, email ou téléphone
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('prenom', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%");
                });
            }

            // Filtre par pays
            if ($request->has('pays')) {
                $query->where('pays', $request->pays);
            }

            // Filtre par ville
            if ($request->has('ville')) {
                $query->where('ville', $request->ville);
            }

            // Tri
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSorts = ['nom', 'prenom', 'email', 'created_at', 'last_login_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            if ($request->has('per_page')) {
                $users = $query->paginate((int) $request->per_page);
            } else {
                $users = $query->get();
            }

            return $this->successResponse($users, 'Liste des utilisateurs récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des utilisateurs', $e->getMessage());
        }
    }
}
