<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke($id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return $this->notFoundResponse('Rôle non trouvé');
            }

            if ($role->name === 'admin') {
                return $this->forbiddenResponse('Le rôle admin ne peut pas être supprimé');
            }

            $usersCount = \DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->count();

            if ($usersCount > 0) {
                return $this->errorResponse(
                    "Ce rôle est encore assigné à {$usersCount} utilisateur(s). Réassignez-les avant de supprimer.",
                    null,
                    409
                );
            }

            Log::info('Rôle supprimé', [
                'role_id' => $role->id,
                'name' => $role->name,
                'deleted_by' => auth()->id(),
            ]);

            // Supprimer via DB pour éviter les erreurs de guard Spatie
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            DB::table('model_has_roles')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();

            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            return $this->successResponse(null, 'Rôle supprimé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du rôle', $e->getMessage());
        }
    }
}
