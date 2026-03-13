<?php

namespace App\Http\Controllers\Users;

use App\Enums\SiteRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateUserRequest $request, $id)
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return $this->notFoundResponse('Utilisateur non trouvé');
            }

            return DB::transaction(function () use ($request, $user) {
                $data = $request->safe()->except(['role', 'site_id', 'site_role']);

                $user->update($data);

                // Si un rôle est fourni, synchroniser (remplace l'ancien)
                if ($request->has('role')) {
                    $user->syncRoles([$request->validated('role')]);
                }

                // Si un site est fourni, réaffecter
                if ($request->has('site_id')) {
                    $newSiteId = $request->validated('site_id');
                    $siteRole  = SiteRole::tryFrom($request->validated('site_role') ?? '') ?? SiteRole::STAFF;

                    if ($newSiteId) {
                        // Retirer l'ancien default
                        $user->userSites()->update(['is_default' => false]);

                        // Attacher (ou mettre à jour) le nouveau site
                        $user->sites()->syncWithoutDetaching([
                            $newSiteId => [
                                'role'       => $siteRole->value,
                                'is_default' => true,
                            ],
                        ]);

                        $user->update(['default_site_id' => $newSiteId]);
                    }
                }

                $user->load('roles');

                return $this->successResponse($user, 'Utilisateur mis à jour avec succès');
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour de l\'utilisateur', $e->getMessage());
        }
    }
}
