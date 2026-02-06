<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->notFoundResponse('Utilisateur non trouvé');
            }

            // Soft delete
            $user->delete();

            Log::info('Utilisateur supprimé (soft delete)', ['user_id' => $id]);

            return $this->successResponse(null, 'Utilisateur supprimé avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de l\'utilisateur', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la suppression de l\'utilisateur', $e->getMessage());
        }
    }
}
