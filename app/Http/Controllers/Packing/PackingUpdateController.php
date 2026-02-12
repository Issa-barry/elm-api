<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Packing\UpdatePackingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;

class PackingUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdatePackingRequest $request, int $id)
    {
        try {
            $packing = Packing::find($id);

            if (!$packing) {
                return $this->notFoundResponse('Packing non trouvé');
            }

            $packing->update($request->validated());
            $packing->load('prestataire');

            return $this->successResponse($packing, 'Packing mis à jour avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du packing', $e->getMessage());
        }
    }
}
