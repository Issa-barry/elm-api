<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\ForfaitResource;
use App\Models\Forfait;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForfaitIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        $forfaits = Forfait::orderBy('prix')->get();

        return $this->successResponse(ForfaitResource::collection($forfaits));
    }
}
