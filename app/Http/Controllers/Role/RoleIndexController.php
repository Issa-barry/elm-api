<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        try {
            $roles = Role::with('permissions')->get();

            $modules = Permission::all()
                ->groupBy(fn($p) => explode('.', $p->name)[0])
                ->map(fn($perms) => $perms->pluck('name')->map(fn($p) => explode('.', $p)[1])->values())
                ->toArray();

            $data = $roles->map(function ($role) use ($modules) {
                $rolePermissions = $role->permissions->pluck('name')->toArray();

                return [
                    'role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'created_at' => $role->created_at,
                    ],
                    'modules' => collect($modules)->map(function ($actions, $module) use ($rolePermissions) {
                        return [
                            'module' => $module,
                            'permissions' => collect(['create', 'read', 'update', 'delete'])
                                ->mapWithKeys(fn($action) => [
                                    $action => in_array("{$module}.{$action}", $rolePermissions),
                                ]),
                        ];
                    })->values(),
                ];
            });

            return $this->successResponse($data, 'Liste des rôles récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des rôles', $e->getMessage());
        }
    }
}
