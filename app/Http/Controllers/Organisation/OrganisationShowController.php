<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganisationResource;
use App\Http\Traits\ApiResponse;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/organisations/{organisation} — Détail d'une organisation (super_admin uniquement).
 */
class OrganisationShowController extends Controller
{
    use ApiResponse;

    public function __invoke(Organisation $organisation): JsonResponse
    {
        return $this->successResponse(
            new OrganisationResource($organisation->loadCount(['sites', 'users'])->load('sites:id,nom,code,type,statut,organisation_id')),
            'Organisation récupérée'
        );
    }
}
