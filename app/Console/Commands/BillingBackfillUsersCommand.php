<?php

namespace App\Console\Commands;

use App\Enums\BillingEventStatus;
use App\Models\OrganisationBillingEvent;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crée les events billing manquants pour les utilisateurs existants.
 *
 * Usage :
 *   php artisan billing:backfill-users
 *   php artisan billing:backfill-users --dry-run
 */
class BillingBackfillUsersCommand extends Command
{
    protected $signature = 'billing:backfill-users
                            {--dry-run : Affiche ce qui serait créé sans écrire en base}';

    protected $description = 'Génère les events billing manquants pour les utilisateurs existants (user_created)';

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $unitPrice = (float) config('billing.user_account_price', 0);

        // Utilisateurs avec organisation_id mais sans event user_created
        $users = User::whereNotNull('organisation_id')
            ->whereNotExists(function ($q) {
                $q->from('organisation_billing_events')
                  ->whereColumn('organisation_billing_events.user_id', 'users.id')
                  ->where('organisation_billing_events.event_type', 'user_created');
            })
            ->withTrashed()
            ->get(['id', 'organisation_id', 'created_at']);

        if ($users->isEmpty()) {
            $this->info('Aucun utilisateur sans event billing. Rien à faire.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s utilisateur(s) sans event billing trouvé(s).%s',
            $users->count(),
            $dryRun ? ' [DRY-RUN — aucune écriture]' : ''
        ));

        $created = 0;
        foreach ($users as $user) {
            $this->line("  → user #{$user->id} (org #{$user->organisation_id}) — {$user->created_at}");

            if (! $dryRun) {
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
                $created++;
            }
        }

        if (! $dryRun) {
            $this->info("✓ {$created} event(s) créé(s).");
        }

        return self::SUCCESS;
    }
}
