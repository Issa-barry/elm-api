<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;

class UserRolesController extends Controller
{
    use ApiResponse;

    public function __invoke($userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return $this->notFoundResponse('Utilisateur non trouvé');
            }

            return $this->successResponse([
                'user_id' => $user->id,
                'nom_complet' => $user->nom_complet,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ], 'Rôles de l\'utilisateur récupérés avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des rôles', $e->getMessage());
        }
    }
}
