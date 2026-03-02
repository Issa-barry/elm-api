<?php

namespace App\Http\Middleware;

use App\Models\Usine;
use App\Services\UsineContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout le contexte usine pour chaque requête API.
 *
 * Priorité de résolution :
 *   1. Header X-Usine-Id (si fourni et autorisé)
 *   2. default_usine_id de l'utilisateur authentifié
 *   3. Rien (UsineContext reste vide → vue consolidée pour le siège)
 *
 * Vérification d'accès :
 *   - Utilisateur siège : accès à TOUTES les usines actives
 *   - Utilisateur non-siège : accès uniquement à ses usines affectées
 */
class ResolveUsineContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Pas d'utilisateur authentifié → on passe directement
        if (!$user) {
            return $next($request);
        }

        /** @var UsineContext $ctx */
        $ctx = app(UsineContext::class);

        $requestedUsineId = $request->header('X-Usine-Id');
        $requestedUsineId = is_string($requestedUsineId) ? trim($requestedUsineId) : $requestedUsineId;

        if ($requestedUsineId !== null && $requestedUsineId !== '') {
            // Sentinel "all" : vue consolidée toutes usines (siège uniquement)
            if (is_string($requestedUsineId) && strcasecmp($requestedUsineId, 'all') === 0) {
                if (!$user->isSiege()) {
                    abort(403, 'Vue consolidée réservée aux utilisateurs siège.');
                }
                $ctx->setAllUsines();
                return $next($request);
            }

            $usineId = (int) $requestedUsineId;

            // L'usine doit exister et être active
            $usine = Usine::find($usineId);

            if (!$usine || !$usine->isActive()) {
                abort(404, 'Usine non trouvée ou inactive.');
            }

            // Vérification d'accès
            if (!$user->isSiege() && !$user->hasUsineAccess($usineId)) {
                abort(403, 'Accès à cette usine non autorisé.');
            }

            $ctx->setCurrentUsineId($usineId);
        } elseif ($user->isSiege()) {
            // Siège sans header explicite => vue consolidée par défaut
            // (utile quand le front choisit "Toutes les usines" en supprimant le header)
            $ctx->setAllUsines();
        } elseif ($user->default_usine_id) {
            // Non-siège sans header → usine par défaut
            $ctx->setCurrentUsineId($user->default_usine_id);
        }
        // Sinon : siège sans X-Usine-Id → pas de filtre → vue consolidée

        return $next($request);
    }
}
