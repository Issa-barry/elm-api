<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;

class PackingDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $packing = Packing::find($id);

            if (!$packing) {
                return $this->notFoundResponse('Packing non trouvé');
            }

            $packing->delete();

            return $this->successResponse(null, 'Packing supprimé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du packing', $e->getMessage());
        }
    }
}
