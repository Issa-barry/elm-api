<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\UpdatePrestataireRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Prestataire;

class PrestataireUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdatePrestataireRequest $request, int $id)
    {
        try {
            $prestataire = Prestataire::find($id);

            if (!$prestataire) {
                return $this->notFoundResponse('Prestataire non trouve');
            }

            $payload = $request->safe()->except(['reference']);
            $prestataire->update($payload);

            return $this->successResponse($prestataire->fresh(), 'Prestataire mis a jour avec succes');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors de la mise a jour du prestataire', $e->getMessage());
        }
    }
}
