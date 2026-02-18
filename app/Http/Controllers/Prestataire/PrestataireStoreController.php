<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\StorePrestataireRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Prestataire;

class PrestataireStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StorePrestataireRequest $request)
    {
        try {
            $payload = $request->safe()->except(['reference']);
            $prestataire = Prestataire::create($payload);

            return $this->createdResponse($prestataire->fresh(), 'Prestataire cree avec succes');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors de la creation du prestataire', $e->getMessage());
        }
    }
}
