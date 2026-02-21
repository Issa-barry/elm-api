<?php

namespace App\Http\Controllers\Proprietaires;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proprietaire\StoreProprietaireRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Proprietaire;

class ProprietaireStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreProprietaireRequest $request)
    {
        $proprietaire = Proprietaire::create($request->validated());

        return $this->createdResponse($proprietaire, 'Propriétaire créé avec succès');
    }
}
