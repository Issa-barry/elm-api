<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
use App\Http\Controllers\Controller;
use App\Http\Requests\Packing\UpdatePackingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackingUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdatePackingRequest $request, int $id)
    {
        try {
            $packing = null;
            $payload = $request->safe()->except(['montant', 'statut']);

            DB::transaction(function () use ($id, $payload, &$packing) {
                $packing = Packing::query()->lockForUpdate()->find($id);

                if (!$packing) {
                    throw new ModelNotFoundException();
                }

                if ($packing->statut !== PackingStatut::IMPAYEE) {
                    throw ValidationException::withMessages([
                        'statut' => ['Seul un packing impayee peut etre modifie.'],
                    ]);
                }

                $ancienNbRouleaux = (int) $packing->nb_rouleaux;

                if (!empty($payload)) {
                    $packing->fill($payload);
                    $packing->save();
                }

                $nouveauNbRouleaux = (int) $packing->nb_rouleaux;
                $deltaRouleaux     = $nouveauNbRouleaux - $ancienNbRouleaux;

                $packing->ajusterStockRouleauxSelonDelta($deltaRouleaux);
            });

            $packing->refresh()->load(['prestataire', 'versements']);

            return $this->successResponse($packing, 'Packing mis a jour avec succes');
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Packing non trouve');
        } catch (ValidationException $e) {
            if (isset($e->errors()['statut'])) {
                return $this->errorResponse('Modification impossible', $e->errors(), 422);
            }
            return $this->validationErrorResponse($e->errors(), 'Les donnees fournies sont invalides.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors de la mise a jour du packing', $e->getMessage());
        }
    }
}
