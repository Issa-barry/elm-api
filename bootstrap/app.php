<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Http\Middleware\Cors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS middleware en premier
        $middleware->prepend(Cors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toujours retourner JSON pour les requêtes API
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Gestion des exceptions d'authentification
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié. Veuillez vous connecter.',
                ], 401);
            }
        });

        // Gestion des exceptions d'autorisation
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé.',
                ], 403);
            }
        });

        // Gestion des modèles non trouvés
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'message' => "{$model} non trouvé(e).",
                ], 404);
            }
        });

        // Gestion des routes non trouvées
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Route non trouvée.',
                ], 404);
            }
        });

        // Gestion des méthodes HTTP non autorisées
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Méthode HTTP non autorisée.',
                    'allowed_methods' => $e->getHeaders()['Allow'] ?? null,
                ], 405);
            }
        });

        // Gestion des erreurs de validation
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les données fournies sont invalides.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Gestion des erreurs de base de données
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // Ne pas exposer les détails SQL en production
                $message = app()->environment('production')
                    ? 'Erreur de base de données.'
                    : 'Erreur de base de données: ' . $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 500);
            }
        });

        // Gestion des erreurs HTTP génériques
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Une erreur est survenue.',
                ], $e->getStatusCode());
            }
        });

        // Gestion de toutes les autres exceptions (fallback)
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = app()->environment('production')
                    ? 'Une erreur interne est survenue.'
                    : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'exception' => app()->environment('production') ? null : get_class($e),
                ], 500);
            }
        });
    })->create();
