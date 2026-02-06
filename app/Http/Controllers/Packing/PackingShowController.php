<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;

class PackingShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $packing = Packing::with('prestataire')->find($id);

            if (!$packing) {
                return $this->notFoundResponse('Packing non trouvé');
            }

            return $this->successResponse($packing, 'Packing récupéré avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération du packing', $e->getMessage());
        }
    }
}
