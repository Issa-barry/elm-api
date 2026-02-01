<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Client;

class ClientDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $client = Client::find($id);

            if (!$client) {
                return $this->notFoundResponse('Client non trouvé');
            }

            $client->delete();

            return $this->successResponse(null, 'Client supprimé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du client', $e->getMessage());
        }
    }
}
