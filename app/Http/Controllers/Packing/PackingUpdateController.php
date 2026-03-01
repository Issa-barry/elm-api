<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
use App\Http\Controllers\Controller;
use App\Http\Requests\Packing\UpdatePackingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackingUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdatePackingRequest $request, int $id)
    {
        try {
            $packing = Packing::find($id);

            if (!$packing) {
                return $this->notFoundResponse('Packing non trouve');
            }

            if ($packing->statut !== PackingStatut::IMPAYEE) {
                return $this->errorResponse(
                    'Modification impossible',
                    ['statut' => ['Seul un packing impayee peut etre modifie.']],
                    422
                );
            }

            $payload = $request->safe()->except(['montant', 'statut']);

            DB::transaction(function () use ($packing, $payload) {
                if (!empty($payload)) {
                    $packing->fill($payload);
                    $packing->save();
                }
            });

            $packing->refresh()->load(['prestataire', 'versements']);

            return $this->successResponse($packing, 'Packing mis a jour avec succes');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Les donnees fournies sont invalides.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors de la mise a jour du packing', $e->getMessage());
        }
    }
}
