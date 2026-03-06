<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\BillingEventResource;
use App\Http\Traits\ApiResponse;
use App\Models\OrganisationBillingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/billing/events — Liste les events de facturation.
 *
 * Filtres query string :
 *   ?organisation_id=X   — filtre par organisation
 *   ?status=pending|invoiced|paid|cancelled
 *
 * Accès réservé au super_admin.
 */
class BillingEventIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'organisation_id' => ['sometimes', 'integer', 'exists:organisations,id'],
            'status'          => ['sometimes', 'string', 'in:pending,invoiced,paid,cancelled'],
        ]);

        $query = OrganisationBillingEvent::with(['organisation.forfait', 'user'])
            ->orderBy('occurred_at', 'desc');

        if ($request->filled('organisation_id')) {
            $query->forOrganisation((int) $request->organisation_id);
        }

        if ($request->filled('status')) {
            $query->withStatus($request->status);
        }

        $events = $query->get();

        return $this->successResponse(
            BillingEventResource::collection($events),
            'Events de facturation récupérés'
        );
    }
}
