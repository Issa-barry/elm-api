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
            $prestataire = Prestataire::create($request->validated());

            return $this->createdResponse($prestataire, 'Prestataire créé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création du prestataire', $e->getMessage());
        }
    }
}
