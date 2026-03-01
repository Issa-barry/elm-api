<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\CheckPhoneRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;

class UserCheckPhoneController extends Controller
{
    use ApiResponse;

    public function __invoke(CheckPhoneRequest $request)
    {
        $phone = $request->validated('phone');

        $exists = User::where('phone', $phone)->exists();

        return $this->successResponse(
            [
                'available'       => !$exists,
                'normalized_phone' => $phone,
            ],
            $exists
                ? 'Ce numéro de téléphone est déjà utilisé.'
                : 'Ce numéro de téléphone est disponible.'
        );
    }
}
