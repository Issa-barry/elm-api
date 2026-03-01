<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutCommissionVente;
use App\Enums\StatutVersementCommission;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommissionVente;
use App\Models\VersementCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VersementCommissionStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id, string $type)
    {
        if (!in_array($type, ['livreur', 'proprietaire'])) {
            return $this->notFoundResponse('Type de bénéficiaire invalide. Valeurs acceptées : livreur, proprietaire');
        }

        $commission = CommissionVente::find($id);

        if (!$commission) {
            return $this->notFoundResponse('Commission non trouvée');
        }

        $statutsEligibles = [
            StatutCommissionVente::ELIGIBLE->value,
            StatutCommissionVente::PARTIELLEMENT_VERSEE->value,
        ];

        if (!in_array($commission->statut->value, $statutsEligibles)) {
            return $this->errorResponse(
                'La commission n\'est pas encore éligible au versement (statut actuel : ' . $commission->statut->value . ').',
                null,
                422
            );
        }

        $versement = VersementCommission::where('commission_vente_id', $id)
            ->where('beneficiaire_type', $type)
            ->first();

        if (!$versement) {
            return $this->notFoundResponse("Aucun versement de type '{$type}' trouvé pour cette commission.");
        }

        if ($versement->statut === StatutVersementCommission::EFFECTUE) {
            return $this->errorResponse('Ce versement a déjà été effectué.', null, 422);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($versement, $commission, $validated) {
            $versement->update([
                'montant_verse' => $versement->montant_attendu,
                'statut'        => StatutVersementCommission::EFFECTUE->value,
                'verse_par'     => auth()->id(),
                'verse_at'      => now(),
                'note'          => $validated['note'] ?? null,
            ]);

            // Recalculer le statut de la commission
            $tousVersesOuAbsents = !$commission->versements()
                ->where('statut', '!=', StatutVersementCommission::EFFECTUE->value)
                ->exists();

            $commission->update([
                'statut' => $tousVersesOuAbsents
                    ? StatutCommissionVente::VERSEE->value
                    : StatutCommissionVente::PARTIELLEMENT_VERSEE->value,
            ]);

            return $this->successResponse(
                $versement->load('commission.versements'),
                'Versement effectué avec succès'
            );
        });
    }
}
