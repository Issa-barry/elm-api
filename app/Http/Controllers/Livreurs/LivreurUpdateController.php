<?php

namespace App\Http\Controllers\Livreurs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livreur\UpdateLivreurRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Livreur;

class LivreurUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateLivreurRequest $request, int $id)
    {
        $livreur = Livreur::find($id);

        if (!$livreur) {
            return $this->notFoundResponse('Livreur non trouvé');
        }

        $livreur->update($request->validated());

        return $this->successResponse($livreur->fresh(), 'Livreur mis à jour');
    }
}
