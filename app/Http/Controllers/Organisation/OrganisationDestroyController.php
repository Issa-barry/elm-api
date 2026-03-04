<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;

/**
 * DELETE /api/v1/organisations/{organisation} — Supprimer une organisation (super_admin uniquement).
 *
 * Soft delete uniquement. La suppression est bloquée si l'organisation
 * possède encore des sites actives (protection anti-orphelins).
 */
class OrganisationDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(Organisation $organisation): JsonResponse
    {
        // Bloquer la suppression si des sites actives sont rattachées
        $sitesActives = $organisation->sites()->whereNull('deleted_at')->count();

        if ($sitesActives > 0) {
            return $this->errorResponse(
                "Impossible de supprimer une organisation ayant {$sitesActives} site(s) actif(s). " .
                "Transférez ou supprimez les sites d'abord.",
                null,
                422
            );
        }

        $organisation->delete();

        return $this->successResponse(null, 'Organisation supprimée avec succès');
    }
}
