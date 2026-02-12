<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Spatie\Permission\Models\Permission;

class PermissionIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        try {
            $permissions = Permission::all(['id', 'name']);

            // Grouper par module pour le frontend
            $grouped = $permissions->groupBy(function ($permission) {
                return explode('.', $permission->name)[0];
            });

            return $this->successResponse([
                'permissions' => $permissions,
                'grouped' => $grouped,
            ], 'Liste des permissions récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des permissions', $e->getMessage());
        }
    }
}
