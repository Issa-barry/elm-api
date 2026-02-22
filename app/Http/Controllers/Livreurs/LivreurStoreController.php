<?php

namespace App\Http\Controllers\Livreurs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livreur\StoreLivreurRequest;
use App\Http\Resources\LivreurResource;
use App\Http\Traits\ApiResponse;
use App\Models\Livreur;

class LivreurStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreLivreurRequest $request)
    {
        $livreur = Livreur::create($request->validated());

        return $this->createdResponse(
            LivreurResource::make($livreur),
            'Livreur créé avec succès'
        );
    }
}