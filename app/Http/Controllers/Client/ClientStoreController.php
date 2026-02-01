<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Client;

class ClientStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreClientRequest $request)
    {
        try {
            $client = Client::create($request->validated());

            return $this->createdResponse($client, 'Client créé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création du client', $e->getMessage());
        }
    }
}
