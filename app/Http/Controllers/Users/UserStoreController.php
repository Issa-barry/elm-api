<?php

namespace App\Http\Controllers\Users;

use App\Enums\BillingEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Traits\ApiResponse;
use App\Enums\SiteRole;
use App\Models\Organisation;
use App\Models\OrganisationBillingEvent;
use App\Models\User;
use App\Services\SiteContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UserStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreUserRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->safe()->except(['role', 'site_id', 'site_role']);

                // organisation_id NOT NULL : si non fourni, hériter de l'admin authentifié
                if (empty($data['organisation_id'])) {
                    $data['organisation_id'] = auth()->user()?->organisation_id;
                }

                // Résoudre le site : request → SiteContext → site par défaut de l'admin
                $siteId = $request->validated('site_id')
                    ?? app(SiteContext::class)->getCurrentSiteId()
                    ?? auth()->user()?->default_site_id;

                if ($siteId) {
                    $data['default_site_id'] = $siteId;
                }

                $user = User::create($data);

                // Assigner le rôle via Spatie (un seul rôle)
                $user->assignRole($request->validated('role'));

                // Attacher le user au site
                if ($siteId) {
                    $siteRole = SiteRole::tryFrom($request->validated('site_role') ?? '') ?? SiteRole::STAFF;
                    $user->sites()->attach($siteId, [
                        'role'       => $siteRole->value,
                        'is_default' => true,
                    ]);
                }

                // Générer l'event de facturation si l'utilisateur est rattaché à une organisation
                $organisationId = $user->organisation_id;

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
        } catch (UniqueConstraintViolationException $e) {
            $message = str_contains($e->getMessage(), 'phone')
                ? 'Ce numéro de téléphone est déjà utilisé par un autre compte.'
                : (str_contains($e->getMessage(), 'email')
                    ? 'Cette adresse email est déjà utilisée par un autre compte.'
                    : 'Un utilisateur avec ces informations existe déjà.');

            return $this->errorResponse($message, null, 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Une erreur inattendue est survenue. Veuillez réessayer.', null, 500);
        }
    }
}
