<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Forfait;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ForfaitDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(Forfait $forfait): JsonResponse
    {
        if ($forfait->organisations()->exists()) {
            return $this->errorResponse(
                'Ce forfait est assigné à une ou plusieurs organisations et ne peut pas être supprimé.',
                422
            );
        }

        $forfait->delete();

        return $this->successResponse(null, 200, 'Forfait supprimé.');
    }
}
