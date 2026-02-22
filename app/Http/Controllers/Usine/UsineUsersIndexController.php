<?php

namespace App\Http\Controllers\Usine;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Usine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/usines/{id}/users
 *
 * Liste les utilisateurs affectés à une usine.
 * - Siège : accès libre
 * - Non-siège : uniquement si affecté à cette usine
 */
class UsineUsersIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $user  = $request->user();
        $usine = Usine::findOrFail($id);

        if (!$user->isSiege() && !$user->hasUsineAccess($id)) {
            return $this->forbiddenResponse('Vous n\'êtes pas affecté à cette usine.');
        }

        $users = $usine->users()
            ->select(['users.id', 'users.nom', 'users.prenom', 'users.email', 'users.phone', 'users.type'])
            ->withPivot(['role', 'is_default'])
            ->orderBy('users.nom')
            ->orderBy('users.prenom')
            ->get()
            ->map(function ($u) {
                $u->role_usine  = $u->pivot->role;
                $u->is_default  = (bool) $u->pivot->is_default;
                return $u;
            });

        return $this->successResponse($users, 'Utilisateurs de l\'usine récupérés');
    }
}
