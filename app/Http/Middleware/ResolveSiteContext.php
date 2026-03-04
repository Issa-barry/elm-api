<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Services\SiteContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout le contexte site pour chaque requête API.
 *
 * Priorité de résolution :
 *   1. Header X-Site-Id (si fourni et autorisé)
 *   2. default_site_id de l'utilisateur authentifié
 *   3. Rien (SiteContext reste vide → vue consolidée pour le siège)
 *
 * Vérification d'accès :
 *   - Utilisateur siège : accès à TOUS les sites actifs
 *   - Utilisateur non-siège : accès uniquement à ses sites affectés
 */
class ResolveSiteContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Pas d'utilisateur authentifié → on passe directement
        if (!$user) {
            return $next($request);
        }

        /** @var SiteContext $ctx */
        $ctx = app(SiteContext::class);
        $ctx->clear();

        $requestedSiteId = $request->header('X-Site-Id');
        $requestedSiteId = is_string($requestedSiteId) ? trim($requestedSiteId) : $requestedSiteId;

        if ($requestedSiteId !== null && $requestedSiteId !== '') {
            // Sentinel "all" : vue consolidée tous sites (siège uniquement)
            if (is_string($requestedSiteId) && strcasecmp($requestedSiteId, 'all') === 0) {
                if (!$user->isSiege()) {
                    abort(403, 'Vue consolidée réservée aux utilisateurs siège.');
                }
                $ctx->setAllSites();
                return $next($request);
            }

            $siteId = (int) $requestedSiteId;

            // Le site doit exister et être actif
            $site = Site::find($siteId);

            if (!$site || !$site->isActive()) {
                abort(404, 'Site non trouvé ou inactif.');
            }

            // Vérification d'accès
            if (!$user->isSiege() && !$user->hasSiteAccess($siteId)) {
                abort(403, 'Accès à ce site non autorisé.');
            }

            $ctx->setCurrentSiteId($siteId);
        } elseif ($user->isSiege()) {
            // Siège sans header explicite => vue consolidée par défaut
            $ctx->setAllSites();
        } elseif ($user->default_site_id) {
            // Non-siège sans header → site par défaut
            $ctx->setCurrentSiteId($user->default_site_id);
        }

        return $next($request);
    }
}
