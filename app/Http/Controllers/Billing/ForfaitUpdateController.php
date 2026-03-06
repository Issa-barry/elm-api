<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forfait\UpdateForfaitRequest;
use App\Http\Resources\ForfaitResource;
use App\Models\Forfait;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ForfaitUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateForfaitRequest $request, Forfait $forfait): JsonResponse
    {
        $forfait->update($request->validated());

        return $this->successResponse(new ForfaitResource($forfait->fresh()));
    }
}
