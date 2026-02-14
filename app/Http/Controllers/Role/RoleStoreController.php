<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'min:2', 'max:50', 'unique:roles,name'],
            ], [
                'name.required' => 'Le nom du rôle est obligatoire',
                'name.unique' => 'Ce nom de rôle existe déjà',
            ]);

            $role = Role::create(['name' => strtolower($validated['name']), 'guard_name' => 'web']);

            Log::info('Rôle créé', [
                'role_id' => $role->id,
                'name' => $role->name,
                'created_by' => auth()->id(),
            ]);

            return $this->createdResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'created_at' => $role->created_at,
                ],
            ], 'Rôle créé avec succès');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création du rôle', $e->getMessage());
        }
    }
}
