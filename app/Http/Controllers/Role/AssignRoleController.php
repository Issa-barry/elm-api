<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AssignRoleController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role' => ['required', 'string', 'exists:roles,name'],
            ], [
                'role.required' => 'Le rôle est obligatoire',
                'role.exists' => 'Ce rôle n\'existe pas',
            ]);

            $user = User::find($userId);

            if (!$user) {
                return $this->notFoundResponse('Utilisateur non trouvé');
            }

            $user->syncRoles([$validated['role']]);

            Log::info('Rôle assigné à l\'utilisateur', [
                'user_id' => $user->id,
                'role' => $validated['role'],
                'assigned_by' => auth()->id(),
            ]);

            return $this->successResponse([
                'user' => $user,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ], 'Rôle assigné avec succès');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Erreur de validation');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'assignation du rôle', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Erreur lors de l\'assignation du rôle', $e->getMessage());
        }
    }
}
