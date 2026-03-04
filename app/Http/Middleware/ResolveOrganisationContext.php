<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Services\OrganisationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout le contexte organisation pour chaque requête.
 *
 * Priorité de résolution :
 *   1. Header X-Organisation-Id (si fourni et autorisé)
 *   2. organisation_id de l'utilisateur authentifié (fallback automatique)
 *   3. Rien (OrganisationContext reste vide)
 *
 * Règles d'accès :
 *   - super_admin : peut spécifier n'importe quelle organisation via le header
 *   - Autres rôles : accès limité à leur organisation d'appartenance uniquement
 *
 * Ce middleware est additionnel et non bloquant pour l'existant.
 * Il peut être appliqué individuellement aux routes organisation.
 */
class ResolveOrganisationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        /** @var OrganisationContext $ctx */
        $ctx = app(OrganisationContext::class);

        $requestedOrgId = $request->header('X-Organisation-Id');
        $requestedOrgId = is_string($requestedOrgId) ? trim($requestedOrgId) : $requestedOrgId;

        if ($requestedOrgId !== null && $requestedOrgId !== '') {
            $orgId = (int) $requestedOrgId;

            $org = Organisation::find($orgId);

            if (!$org || !$org->isActive()) {
                abort(404, 'Organisation non trouvée ou inactive.');
            }

            // Seul super_admin peut accéder à une organisation différente de la sienne
            if (!$user->hasRole('super_admin') && (int) $user->organisation_id !== $orgId) {
                abort(403, 'Accès à cette organisation non autorisé.');
            }

            $ctx->setCurrentOrganisationId($orgId);
        } elseif ($user->organisation_id) {
            // Fallback : organisation d'appartenance de l'utilisateur
            $ctx->setCurrentOrganisationId((int) $user->organisation_id);
        }

        return $next($request);
    }
}
