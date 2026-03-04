<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/sites/{id}/users
 *
 * Liste les utilisateurs affectés à un site.
 * - Siège : accès libre
 * - Non-siège : uniquement si affecté à ce site
 */
class SiteUsersIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $site = Site::findOrFail($id);

        if (!$user->isSiege() && !$user->hasSiteAccess($id)) {
            return $this->forbiddenResponse('Vous n\'êtes pas affecté à ce site.');
        }

        $users = $site->users()
            ->select(['users.id', 'users.nom', 'users.prenom', 'users.email', 'users.phone', 'users.type'])
            ->withPivot(['role', 'is_default'])
            ->orderBy('users.nom')
            ->orderBy('users.prenom')
            ->get()
            ->map(function ($u) {
                $u->role_site   = $u->pivot->role;
                $u->is_default  = (bool) $u->pivot->is_default;
                return $u;
            });

        return $this->successResponse($users, 'Utilisateurs du site récupérés');
    }
}
