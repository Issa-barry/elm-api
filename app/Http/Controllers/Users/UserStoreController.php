<?php

namespace App\Http\Controllers\Users;

use App\Enums\BillingEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Organisation;
use App\Models\OrganisationBillingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreUserRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->safe()->except(['role']);

                $user = User::create($data);

                // Assigner le rôle via Spatie (un seul rôle)
                $user->assignRole($request->validated('role'));

                // Générer l'event de facturation si l'utilisateur est rattaché à une organisation
                $organisationId = $user->organisation_id
                    ?? auth()->user()?->organisation_id;

                if ($organisationId) {
                    $org = Organisation::with('forfait')->find($organisationId);
                    $unitPrice = (float) ($org?->forfait?->prix ?? config('billing.user_account_price', 0));

                    OrganisationBillingEvent::firstOrCreate(
                        [
                            'event_type' => 'user_created',
                            'user_id'    => $user->id,
                        ],
                        [
                            'organisation_id' => $organisationId,
                            'unit_price'      => $unitPrice,
                            'quantity'        => 1,
                            'amount'          => $unitPrice,
                            'status'          => BillingEventStatus::PENDING,
                            'occurred_at'     => now(),
                        ]
                    );
                }

                $user->load('roles');

                return $this->createdResponse($user, 'Utilisateur créé avec succès');
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création de l\'utilisateur', $e->getMessage());
        }
    }
}
