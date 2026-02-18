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

            $payload = $request->safe()->except(['montant']);
            $targetStatut = $payload['statut'] ?? null;
            unset($payload['statut']);

            $lockedFields = ['prestataire_id', 'date', 'nb_rouleaux', 'prix_par_rouleau'];
            if (
                $packing->statut === PackingStatut::VALIDE
                && $request->hasAny($lockedFields)
                && $targetStatut !== PackingStatut::ANNULE->value
            ) {
                return $this->errorResponse(
                    'Transition invalide',
                    ['statut' => ['Un packing valide doit etre annule avant modification des champs metier.']],
                    422
                );
            }

            if (
                $targetStatut === PackingStatut::A_VALIDER->value
                && $packing->statut === PackingStatut::VALIDE
            ) {
                return $this->errorResponse(
                    'Transition invalide',
                    ['statut' => ['Utilisez annuler() pour compenser le stock avant de revenir a a_valider.']],
                    422
                );
            }

            DB::transaction(function () use ($packing, $payload, $targetStatut) {
                if (!empty($payload)) {
                    $packing->fill($payload);
                    $packing->save();
                }

                if ($targetStatut === PackingStatut::VALIDE->value) {
                    $packing->valider();
                    return;
                }

                if ($targetStatut === PackingStatut::ANNULE->value) {
                    $packing->annuler();
                    return;
                }

                if ($targetStatut === PackingStatut::A_VALIDER->value) {
                    $packing->statut = PackingStatut::A_VALIDER;
                    $packing->save();
                }
            });

            $packing->refresh()->load(['prestataire', 'facture']);

            return $this->successResponse($packing, 'Packing mis a jour avec succes');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Les donnees fournies sont invalides.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors de la mise a jour du packing', $e->getMessage());
        }
    }
}
