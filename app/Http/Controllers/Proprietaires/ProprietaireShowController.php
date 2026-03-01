<?php

namespace App\Http\Controllers\Proprietaires;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProprietaireResource;
use App\Http\Traits\ApiResponse;
use App\Models\Proprietaire;

class ProprietaireShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $proprietaire = Proprietaire::find($id);

        if (!$proprietaire) {
            return $this->notFoundResponse('Propriétaire non trouvé');
        }

        return $this->successResponse(ProprietaireResource::make($proprietaire));
    }
}