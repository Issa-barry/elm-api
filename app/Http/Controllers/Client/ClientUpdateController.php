<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Client;

class ClientUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateClientRequest $request, int $id)
    {
        try {
            $client = Client::find($id);

            if (!$client) {
                return $this->notFoundResponse('Client non trouvé');
            }

            $client->update($request->validated());

            return $this->successResponse($client->fresh(), 'Client mis à jour avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du client', $e->getMessage());
        }
    }
}
