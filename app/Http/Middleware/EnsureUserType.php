<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserType
{
    /**
     * Vérifie que le type du compte utilisateur correspond aux types autorisés.
     *
     * Usage : middleware('user.type:staff,client')
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Accès refusé.');
        }

        $userType = $user->type instanceof UserType
            ? $user->type->value
            : $user->type;

        if (! in_array($userType, $types, true)) {
            abort(403, 'Votre type de compte ne permet pas d\'accéder à cette ressource.');
        }

        return $next($request);
    }
}
