<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Client;

class ClientShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $client = Client::find($id);

            if (!$client) {
                return $this->notFoundResponse('Client non trouvé');
            }

            return $this->successResponse($client, 'Client récupéré avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération du client', $e->getMessage());
        }
    }
}
