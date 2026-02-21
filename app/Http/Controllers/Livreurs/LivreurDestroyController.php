<?php

namespace App\Http\Controllers\Livreurs;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Livreur;

class LivreurDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $livreur = Livreur::find($id);

        if (!$livreur) {
            return $this->notFoundResponse('Livreur non trouvé');
        }

        $livreur->delete();

        return $this->successResponse(null, 'Livreur supprimé');
    }
}
