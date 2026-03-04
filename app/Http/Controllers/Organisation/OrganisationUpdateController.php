<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\OrganisationUpdateRequest;
use App\Http\Resources\OrganisationResource;
use App\Http\Traits\ApiResponse;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;

/**
 * PUT /api/v1/organisations/{organisation} — Mettre à jour une organisation (super_admin uniquement).
 */
class OrganisationUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(OrganisationUpdateRequest $request, Organisation $organisation): JsonResponse
    {
        $organisation->update($request->validated());

        return $this->successResponse(
            new OrganisationResource($organisation->fresh()->loadCount(['sites', 'users'])),
            'Organisation mise à jour avec succès'
        );
    }
}
