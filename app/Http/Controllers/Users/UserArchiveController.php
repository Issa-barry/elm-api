<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserArchiveController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->notFoundResponse('Utilisateur non trouvé');
            }

            if ($user->is_archived) {
                return $this->errorResponse('Cet utilisateur est déjà archivé.', null, 422);
            }

            $user->update([
                'is_archived' => true,
                'is_active'   => false,
                'archived_at' => now(),
            ]);

            Log::info('Utilisateur archivé', ['user_id' => $id]);

            return $this->successResponse($user->fresh(), 'Utilisateur archivé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'archivage', $e->getMessage());
        }
    }
}
