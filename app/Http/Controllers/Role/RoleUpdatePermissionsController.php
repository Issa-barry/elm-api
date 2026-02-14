<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleUpdatePermissionsController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, $id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return $this->notFoundResponse('Rôle non trouvé');
            }

            $validated = $request->validate([
                'permissions' => ['required', 'array'],
                'permissions.*' => ['array'],
                'permissions.*.*' => ['string', 'in:create,read,update,delete'],
            ], [
                'permissions.required' => 'Les permissions sont obligatoires',
            ]);

            // Construire la liste des permissions à partir de la matrice
            $permissionNames = [];
            foreach ($validated['permissions'] as $module => $actions) {
                foreach ($actions as $action) {
                    $permissionNames[] = "{$module}.{$action}";
                }
            }

            // Vérifier que toutes les permissions existent
            $existingPermissions = Permission::whereIn('name', $permissionNames)->pluck('name')->toArray();
            $invalid = array_diff($permissionNames, $existingPermissions);

            if (!empty($invalid)) {
                return $this->validationErrorResponse(
                    ['permissions' => ['Permissions invalides : ' . implode(', ', $invalid)]],
                    'Certaines permissions n\'existent pas'
                );
            }

            $role->syncPermissions($existingPermissions);

            Log::info('Permissions mises à jour', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions_count' => count($existingPermissions),
                'updated_by' => auth()->id(),
            ]);

            // Retourner la matrice mise à jour
            $allModules = Permission::all()
                ->groupBy(fn($p) => explode('.', $p->name)[0])
                ->keys()->toArray();

            $rolePermissions = $role->permissions()->pluck('name')->toArray();

            $modules = collect($allModules)->map(function ($module) use ($rolePermissions) {
                return [
                    'module' => $module,
                    'permissions' => collect(['create', 'read', 'update', 'delete'])
                        ->mapWithKeys(fn($action) => [
                            $action => in_array("{$module}.{$action}", $rolePermissions),
                        ]),
                ];
            })->values();

            return $this->successResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                ],
                'modules' => $modules,
            ], 'Permissions mises à jour avec succès');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour des permissions', $e->getMessage());
        }
    }
}
