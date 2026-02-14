<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Packing\StorePackingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;

class PackingStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StorePackingRequest $request)
    {
        try {
            $packing = Packing::create($request->validated());
            $packing->load(['prestataire', 'facture']);

            return $this->createdResponse([
                'packing' => $packing,
                'facture' => $packing->facture,
            ], 'Packing créé et facture générée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création du packing', $e->getMessage());
        }
    }
}
 