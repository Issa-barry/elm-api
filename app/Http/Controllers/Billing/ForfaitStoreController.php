<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forfait\StoreForfaitRequest;
use App\Http\Resources\ForfaitResource;
use App\Models\Forfait;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ForfaitStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreForfaitRequest $request): JsonResponse
    {
        $forfait = Forfait::create($request->validated());

        return $this->successResponse(new ForfaitResource($forfait), 201);
    }
}
