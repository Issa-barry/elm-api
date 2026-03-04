<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PATCH /api/v1/sites/{siteId}/set-default
 *
 * Permet à l'utilisateur connecté de changer son site par défaut,
 * parmi les sites auxquels il est affecté.
 */
class SiteChangeDefaultController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $siteId): JsonResponse
    {
        $user = $request->user();

        // Vérifier que le site est accessible par l'utilisateur
        if (!$user->isSiege() && !$user->hasSiteAccess($siteId)) {
            return $this->forbiddenResponse('Vous n\'êtes pas affecté à ce site.');
        }

        // Réinitialiser l'ancien is_default
        $user->userSites()->update(['is_default' => false]);

        // Mettre à jour le pivot pour le site choisi
        $user->sites()->updateExistingPivot($siteId, ['is_default' => true]);

        // Mettre à jour la colonne dénormalisée
        $user->update(['default_site_id' => $siteId]);

        return $this->successResponse(
            ['default_site_id' => $siteId],
            'Site par défaut mis à jour'
        );
    }
}
