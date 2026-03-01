<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;

class PackingVersementIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $packing = Packing::with(['versements.creator', 'prestataire'])->find($id);

        if (!$packing) {
            return $this->notFoundResponse('Packing non trouve');
        }

        return $this->successResponse([
            'packing'    => $packing,
            'versements' => $packing->versements,
        ]);
    }
}
