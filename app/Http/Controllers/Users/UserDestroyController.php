<?php

namespace App\Http\Controllers\Users;

use App\Enums\SiteRole;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use App\Models\Produit;
use App\Models\User;
use App\Models\UserSite;
use App\Models\Versement;
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

            $hasActivity = Packing::where('created_by', $user->id)->orWhere('updated_by', $user->id)->exists()
                || Produit::where('created_by', $user->id)->orWhere('updated_by', $user->id)->exists()
                || Versement::where('created_by', $user->id)->exists();

            if ($hasActivity) {
                return $this->errorResponse(
                    'Cet utilisateur a des données liées (packings, produits ou versements). Utilisez l\'archivage à la place.',
                    ['action' => 'archive'],
                    422
                );
            }

            $hasSiegeRole = UserSite::where('user_id', $user->id)
                ->whereIn('role', SiteRole::siegeRoles())
                ->exists();

            if ($hasSiegeRole) {
                $otherAdminExists = UserSite::where('user_id', '!=', $user->id)
                    ->whereIn('role', SiteRole::siegeRoles())
                    ->exists();

                if (!$otherAdminExists) {
                    return $this->errorResponse(
                        'Impossible de supprimer cet utilisateur : c\'est le dernier administrateur du système.',
                        null,
                        422
                    );
                }
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
