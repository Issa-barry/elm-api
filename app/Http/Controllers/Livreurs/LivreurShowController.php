<?php

namespace App\Http\Controllers\Livreurs;

use App\Http\Controllers\Controller;
use App\Http\Resources\LivreurResource;
use App\Http\Traits\ApiResponse;
use App\Models\Livreur;

class LivreurShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $livreur = Livreur::find($id);

        if (!$livreur) {
            return $this->notFoundResponse('Livreur non trouvé');
        }

        return $this->successResponse(LivreurResource::make($livreur));
    }
}