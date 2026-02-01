<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Client;

class ClientToggleStatusController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $client = Client::find($id);

            if (!$client) {
                return $this->notFoundResponse('Client non trouvé');
            }

            $client->update(['is_active' => !$client->is_active]);

            $status = $client->is_active ? 'activé' : 'désactivé';

            return $this->successResponse($client->fresh(), "Client {$status} avec succès");
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du changement de statut', $e->getMessage());
        }
    }
}
