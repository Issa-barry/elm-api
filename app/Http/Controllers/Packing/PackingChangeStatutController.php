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
                'statut.enum' => 'Le statut doit etre : a_valider, valide ou annule.',
            ]);

            $newStatut = $validated['statut'];

            if ($newStatut === PackingStatut::VALIDE->value) {
                $facture = $packing->valider();
                $packing->refresh()->load(['prestataire', 'facture']);

                return $this->successResponse([
                    'packing' => $packing,
                    'facture' => $facture?->fresh(['prestataire', 'packings']) ?? $packing->facture,
                ], 'Packing valide avec succes');
            }

            if ($newStatut === PackingStatut::ANNULE->value) {
                $packing->annuler();
                $packing->refresh()->load(['prestataire', 'facture']);

                return $this->successResponse($packing, 'Packing annule avec succes');
            }

            $packing->update(['statut' => PackingStatut::A_VALIDER]);
            $packing->refresh()->load(['prestataire', 'facture']);

            return $this->successResponse($packing, 'Statut du packing mis a jour avec succes');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Les donnees fournies sont invalides.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors du changement de statut', $e->getMessage());
        }
    }
}
