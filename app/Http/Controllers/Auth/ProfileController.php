<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
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
        return $this->successResponse($request->user(), 'Profil récupéré avec succès');
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'nom' => ['sometimes', 'string', 'min:2', 'max:100'],
                'prenom' => ['sometimes', 'string', 'min:2', 'max:100'],
                'email' => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'ville' => ['sometimes', 'string', 'min:2', 'max:100'],
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
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la mise à jour du profil', $e->getMessage());
        }
    }
}