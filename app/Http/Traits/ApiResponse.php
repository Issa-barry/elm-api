<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Réponse de succès
     */
    protected function successResponse($data = null, string $message = 'Opération réussie', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Réponse d'erreur
     */
    protected function errorResponse(string $message = 'Une erreur est survenue', $errors = null, int $code = 500): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Réponse de validation échouée
     */
    protected function validationErrorResponse($errors, string $message = 'Erreur de validation'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], 422);
    }

    /**
     * Réponse non trouvé
     */
    protected function notFoundResponse(string $message = 'Ressource non trouvée'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 404);
    }

    /**
     * Réponse de création réussie
     */
    protected function createdResponse($data, string $message = 'Créé avec succès'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 201);
    }

    /**
     * Réponse non autorisé
     */
    protected function unauthorizedResponse(string $message = 'Non autorisé'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 401);
    }

    /**
     * Réponse interdit
     */
    protected function forbiddenResponse(string $message = 'Accès interdit'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }
}