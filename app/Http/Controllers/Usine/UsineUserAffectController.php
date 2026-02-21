<?php

namespace App\Http\Controllers\Usine;

use App\Enums\UsineRole;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Models\Usine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion des affectations user ↔ usine.
 *
 * POST   /api/v1/usines/{usineId}/users         → affecter un user
 * DELETE /api/v1/usines/{usineId}/users/{userId} → retirer un user
 */
class UsineUserAffectController extends Controller
{
    use ApiResponse;

    /** Affecter un utilisateur à une usine */
    public function attach(Request $request, int $usineId): JsonResponse
    {
        if (!$request->user()->isSiege()) {
            return $this->forbiddenResponse('Seul le siège peut affecter des utilisateurs.');
        }

        $usine = Usine::findOrFail($usineId);

        $validated = $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'role'       => ['required', Rule::enum(UsineRole::class)],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        // Si is_default = true, retirer l'ancien is_default de ce user
        if (!empty($validated['is_default'])) {
            $user->userUsines()->update(['is_default' => false]);
            $user->update(['default_usine_id' => $usineId]);
        }

        $user->usines()->syncWithoutDetaching([
            $usineId => [
                'role'       => $validated['role'],
                'is_default' => $validated['is_default'] ?? false,
            ],
        ]);

        return $this->successResponse(
            $usine->users()->withPivot(['role', 'is_default'])->find($validated['user_id']),
            'Utilisateur affecté à l\'usine avec succès'
        );
    }

    /** Retirer un utilisateur d'une usine */
    public function detach(Request $request, int $usineId, int $userId): JsonResponse
    {
        if (!$request->user()->isSiege()) {
            return $this->forbiddenResponse('Seul le siège peut retirer des utilisateurs.');
        }

        Usine::findOrFail($usineId);
        $user = User::findOrFail($userId);

        $user->usines()->detach($usineId);

        // Si l'usine retirée était la default, mettre à null
        if ($user->default_usine_id === $usineId) {
            $newDefault = $user->userUsines()
                ->where('is_default', true)
                ->value('usine_id');

            $user->update(['default_usine_id' => $newDefault]);
        }

        return $this->successResponse(null, 'Utilisateur retiré de l\'usine avec succès');
    }
}
