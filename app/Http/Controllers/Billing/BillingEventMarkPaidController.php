<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BillingEventResource;
use App\Http\Traits\ApiResponse;
use App\Models\OrganisationBillingEvent;
use Illuminate\Http\JsonResponse;

/**
 * PATCH /api/v1/billing/events/{event}/paid — Marquer un event comme payé.
 *
 * Accès réservé au super_admin.
 */
class BillingEventMarkPaidController extends Controller
{
    use ApiResponse;

    public function __invoke(OrganisationBillingEvent $event): JsonResponse
    {
        if ($event->status === BillingEventStatus::PAID) {
            return $this->errorResponse('Cet event est déjà marqué comme payé.', null, 422);
        }

        if ($event->status === BillingEventStatus::CANCELLED) {
            return $this->errorResponse('Un event annulé ne peut pas être marqué comme payé.', null, 422);
        }

        $event->update(['status' => BillingEventStatus::PAID]);

        return $this->successResponse(
            new BillingEventResource($event->load(['organisation', 'user'])),
            'Event marqué comme payé'
        );
    }
}
