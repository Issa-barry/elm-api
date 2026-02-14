<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, $id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return $this->notFoundResponse('Rôle non trouvé');
            }

            if ($role->name === 'admin') {
                return $this->forbiddenResponse('Le rôle admin ne peut pas être modifié');
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'min:2', 'max:50', 'unique:roles,name,' . $role->id],
            ], [
                'name.required' => 'Le nom du rôle est obligatoire',
                'name.unique' => 'Ce nom de rôle existe déjà',
            ]);

            $role->update(['name' => strtolower($validated['name'])]);

            Log::info('Rôle modifié', [
                'role_id' => $role->id,
                'name' => $role->name,
                'updated_by' => auth()->id(),
            ]);

            return $this->successResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'created_at' => $role->created_at,
                ],
            ], 'Rôle modifié avec succès');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la modification du rôle', $e->getMessage());
        }
    }
}
