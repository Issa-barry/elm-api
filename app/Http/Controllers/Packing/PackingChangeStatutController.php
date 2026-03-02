<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackingChangeStatutController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id)
    {
        try {
            $packing = Packing::find($id);

            if (!$packing) {
                return $this->notFoundResponse('Packing non trouve');
            }

            $validated = $request->validate([
                'statut' => ['required', Rule::enum(PackingStatut::class)],
            ], [
                'statut.required' => 'Le statut est obligatoire.',
                'statut.enum' => 'Le statut doit etre : impayee, partielle, payee ou annulee.',
            ]);

            $newStatut = $validated['statut'];

            if (in_array($newStatut, [PackingStatut::PARTIELLE->value, PackingStatut::PAYEE->value], true)) {
                return $this->errorResponse(
                    'Transition invalide',
                    ['statut' => ['Les statuts partielle et payee sont calcules automatiquement via les versements.']],
                    422
                );
            }

            if ($newStatut === PackingStatut::IMPAYEE->value) {
                if ($packing->statut === PackingStatut::ANNULEE) {
                    $packing->reactiver();
                }
                $packing->refresh()->load(['prestataire', 'versements']);

                return $this->successResponse($packing, 'Packing passe en impayee avec succes');
            }

            if ($newStatut === PackingStatut::ANNULEE->value) {
                if ($packing->statut === PackingStatut::PAYEE) {
                    return $this->errorResponse(
                        'Un packing payé ne peut pas être annulé. Supprimez d\'abord les versements associés.',
                        null,
                        422
                    );
                }

                $packing->annuler();
                $packing->refresh()->load(['prestataire', 'versements']);

                return $this->successResponse($packing, 'Packing annule avec succes');
            }

            $packing->refresh()->load(['prestataire', 'versements']);

            return $this->successResponse($packing, 'Statut du packing mis a jour avec succes');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Les donnees fournies sont invalides.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors du changement de statut', $e->getMessage());
        }
    }
}
