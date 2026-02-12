<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Spatie\Permission\Models\Role;

class RoleShowController extends Controller
{
    use ApiResponse;

    public function __invoke($id)
    {
        try {
            $role = Role::with('permissions:id,name')->find($id);

            if (!$role) {
                return $this->notFoundResponse('Rôle non trouvé');
            }

            return $this->successResponse($role, 'Rôle récupéré avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération du rôle', $e->getMessage());
        }
    }
}
