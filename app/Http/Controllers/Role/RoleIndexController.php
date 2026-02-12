<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Spatie\Permission\Models\Role;

class RoleIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        try {
            $roles = Role::with('permissions:id,name')->get(['id', 'name', 'created_at']);

            return $this->successResponse($roles, 'Liste des rôles récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des rôles', $e->getMessage());
        }
    }
}
