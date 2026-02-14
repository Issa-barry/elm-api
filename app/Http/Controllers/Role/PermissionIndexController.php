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
            $modules = Permission::all()
                ->groupBy(fn($p) => explode('.', $p->name)[0])
                ->map(function ($perms, $module) {
                    $actions = $perms->pluck('name')->map(fn($p) => explode('.', $p)[1])->values();

                    return [
                        'module' => $module,
                        'actions' => $actions,
                        'permissions' => collect(['create', 'read', 'update', 'delete'])
                            ->mapWithKeys(fn($action) => [$action => false]),
                    ];
                })->values();

            return $this->successResponse($modules, 'Liste des permissions récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des permissions', $e->getMessage());
        }
    }
}
