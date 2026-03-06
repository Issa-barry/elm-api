<?php

namespace Database\Seeders;

use App\Enums\BillingEventStatus;
use App\Models\OrganisationBillingEvent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Génère les events billing manquants pour les utilisateurs seedés.
 *
 * Idempotent : firstOrCreate — safe à rejouer.
 */
class BillingBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $unitPrice = (float) config('billing.user_account_price', 0);

        User::whereNotNull('organisation_id')
            ->whereNotExists(function ($q) {
                $q->from('organisation_billing_events')
                  ->whereColumn('organisation_billing_events.user_id', 'users.id')
                  ->where('organisation_billing_events.event_type', 'user_created');
            })
            ->withTrashed()
            ->each(function (User $user) use ($unitPrice) {
                OrganisationBillingEvent::firstOrCreate(
                    ['event_type' => 'user_created', 'user_id' => $user->id],
                    [
                        'organisation_id' => $user->organisation_id,
                        'unit_price'      => $unitPrice,
                        'quantity'        => 1,
                        'amount'          => $unitPrice,
                        'status'          => BillingEventStatus::PENDING,
                        'occurred_at'     => $user->created_at,
                    ]
                );
            });
    }
}
