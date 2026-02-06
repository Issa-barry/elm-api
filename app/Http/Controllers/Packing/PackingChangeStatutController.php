<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackingChangeStatutController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id)
    {
        try {
            $packing = Packing::find($id);

            if (!$packing) {
                return $this->notFoundResponse('Packing non trouvé');
            }

            $validated = $request->validate([
                'statut' => ['required', Rule::in(array_keys(Packing::STATUTS))],
            ], [
                'statut.required' => 'Le statut est obligatoire.',
                'statut.in' => 'Le statut doit être : en_cours, termine, paye ou annule.',
            ]);

            $packing->update(['statut' => $validated['statut']]);
            $packing->load('prestataire');

            return $this->successResponse($packing, 'Statut du packing mis à jour avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du changement de statut', $e->getMessage());
        }
    }
}
