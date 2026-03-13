<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Site;
use App\Services\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use ApiResponse;

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var SiteContext $ctx */
        $ctx = app(SiteContext::class);

        // Charger les sites accessibles avec le rôle du pivot
        $accessibleSites = $user->sites()
            ->select('sites.id', 'sites.nom', 'sites.code', 'sites.type', 'sites.statut')
            ->get()
            ->map(function ($site) {
                return [
                    'id'         => $site->id,
                    'nom'        => $site->nom,
                    'code'       => $site->code,
                    'type'       => $site->type,
                    'statut'     => $site->statut,
                    'mon_role'   => $site->pivot->role,
                    'is_default' => (bool) $site->pivot->is_default,
                ];
            });

        // Fallback : si aucun site via user_sites, utiliser le site courant ou default_site_id
        if ($accessibleSites->isEmpty()) {
            $fallbackSiteId = $ctx->getCurrentSiteId() ?? $user->default_site_id;
            if ($fallbackSiteId) {
                $fallbackSite = Site::find($fallbackSiteId);
                if ($fallbackSite) {
                    $accessibleSites = collect([[
                        'id'         => $fallbackSite->id,
                        'nom'        => $fallbackSite->nom,
                        'code'       => $fallbackSite->code,
                        'type'       => $fallbackSite->type,
                        'statut'     => $fallbackSite->statut,
                        'mon_role'   => null,
                        'is_default' => true,
                    ]]);
                }
            }
        }

        return $this->successResponse([
            'user'             => $user,
            'roles'            => $user->getRoleNames(),
            'permissions'      => $user->getAllPermissions()->pluck('name'),
            'accessible_sites' => $accessibleSites,
            'default_site_id'  => $user->default_site_id,
            'current_site_id'  => $ctx->getCurrentSiteId(),
            'is_siege_user'    => $user->isSiege(),
        ], 'Profil récupéré avec succès');
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $normalized = [];

            if ($request->exists('email')) {
                $normalized['email'] = $this->normalizeEmail($request->input('email'));
            }

            if ($request->exists('ville')) {
                $normalized['ville'] = $this->normalizeLocation($request->input('ville'));
            }

            if ($request->exists('quartier')) {
                $normalized['quartier'] = $this->normalizeLocation($request->input('quartier'));
            }

            if ($normalized !== []) {
                $request->merge($normalized);
            }

            $validated = $request->validate([
                'nom'      => ['sometimes', 'string', 'min:2', 'max:100'],
                'prenom'   => ['sometimes', 'string', 'min:2', 'max:100'],
                'email'    => ['sometimes', 'nullable', 'email:rfc,dns', 'max:255', 'unique:users,email,' . $user->id],
                'ville'    => ['sometimes', 'string', 'min:2', 'max:100'],
                'quartier' => ['sometimes', 'string', 'min:2', 'max:100'],
            ]);

            DB::beginTransaction();
            $user->update($validated);
            DB::commit();

            Log::info('Profil mis à jour', ['user_id' => $user->id]);

            return $this->successResponse($user->fresh(), 'Profil mis à jour avec succès');

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors(), 'Erreur de validation');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de la mise à jour du profil', [
                'user_id' => $request->user()->id,
                'error'   => $e->getMessage(),
            ]);

            return $this->errorResponse('Erreur lors de la mise à jour du profil', $e->getMessage());
        }
    }

    private function normalizeEmail($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : strtolower($normalized);
    }

    private function normalizeLocation($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }
}
