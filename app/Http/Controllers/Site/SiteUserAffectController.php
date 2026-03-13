<?php

namespace App\Http\Controllers\Site;

use App\Enums\SiteRole;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion des affectations user ↔ site.
 *
 * POST   /api/v1/sites/{siteId}/users         → affecter un user
 * DELETE /api/v1/sites/{siteId}/users/{userId} → retirer un user
 */
class SiteUserAffectController extends Controller
{
    use ApiResponse;

    /** Affecter un utilisateur à un site */
    public function attach(Request $request, int $siteId): JsonResponse
    {
        if (!$request->user()->isSiege()) {
            return $this->forbiddenResponse('Seul le siège peut affecter des utilisateurs.');
        }

        $site = Site::findOrFail($siteId);

        $validated = $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'role'       => ['nullable', Rule::enum(SiteRole::class)],
            'is_default' => ['nullable', 'boolean'],
        ]);

        // Valeur par défaut si le frontend n'envoie pas de rôle site
        $validated['role'] ??= SiteRole::STAFF->value;

        $user = User::findOrFail($validated['user_id']);

        // Si is_default = true, retirer l'ancien is_default de ce user
        if (!empty($validated['is_default'])) {
            $user->userSites()->update(['is_default' => false]);
            $user->update(['default_site_id' => $siteId]);
        }

        $user->sites()->syncWithoutDetaching([
            $siteId => [
                'role'       => $validated['role'],
                'is_default' => $validated['is_default'] ?? false,
            ],
        ]);

        return $this->successResponse(
            $site->users()->withPivot(['role', 'is_default'])->find($validated['user_id']),
            'Utilisateur affecté au site avec succès'
        );
    }

    /** Retirer un utilisateur d'un site */
    public function detach(Request $request, int $siteId, int $userId): JsonResponse
    {
        if (!$request->user()->isSiege()) {
            return $this->forbiddenResponse('Seul le siège peut retirer des utilisateurs.');
        }

        Site::findOrFail($siteId);
        $user = User::findOrFail($userId);

        $user->sites()->detach($siteId);

        // Si le site retiré était le default, mettre à null
        if ($user->default_site_id === $siteId) {
            $newDefault = $user->userSites()
                ->where('is_default', true)
                ->value('site_id');

            $user->update(['default_site_id' => $newDefault]);
        }

        return $this->successResponse(null, 'Utilisateur retiré du site avec succès');
    }
}
