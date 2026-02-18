<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Illuminate\Validation\ValidationException;

class PackingValiderController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $packing = Packing::find($id);

            if (!$packing) {
                return $this->notFoundResponse('Packing non trouve');
            }

            if ($packing->statut !== PackingStatut::A_VALIDER) {
                return $this->errorResponse(
                    'Seuls les packings a valider peuvent etre valides',
                    null,
                    422
                );
            }

            $facture = $packing->valider();
            $packing->refresh()->load(['prestataire', 'facture']);

            return $this->successResponse([
                'packing' => $packing,
                'facture' => $facture?->fresh(['prestataire', 'packings']) ?? $packing->facture,
            ], 'Packing valide avec succes');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Les donnees fournies sont invalides.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors de la validation du packing', $e->getMessage());
        }
    }
}
