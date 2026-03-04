<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\OrganisationStoreRequest;
use App\Http\Resources\OrganisationResource;
use App\Http\Traits\ApiResponse;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/organisations — Créer une organisation (super_admin uniquement).
 */
class OrganisationStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(OrganisationStoreRequest $request): JsonResponse
    {
        $organisation = Organisation::create($request->validated());

        return $this->createdResponse(
            new OrganisationResource($organisation->loadCount(['sites', 'users'])),
            'Organisation créée avec succès'
        );
    }
}
