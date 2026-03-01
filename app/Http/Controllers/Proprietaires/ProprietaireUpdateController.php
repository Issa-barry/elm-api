<?php

namespace App\Http\Controllers\Proprietaires;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proprietaire\UpdateProprietaireRequest;
use App\Http\Resources\ProprietaireResource;
use App\Http\Traits\ApiResponse;
use App\Models\Proprietaire;

class ProprietaireUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateProprietaireRequest $request, int $id)
    {
        $proprietaire = Proprietaire::find($id);

        if (!$proprietaire) {
            return $this->notFoundResponse('Propriétaire non trouvé');
        }

        $proprietaire->update($request->validated());

        return $this->successResponse(
            ProprietaireResource::make($proprietaire->fresh()),
            'Propriétaire mis à jour'
        );
    }
}