<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutCommissionVente;
use App\Enums\StatutVersementCommission;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommissionVente;
use App\Models\PaiementVersementCommission;
use App\Models\VersementCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre un paiement (partiel ou total) sur un versement de commission.
 *
 * POST /api/v1/ventes/commissions/{id}/versements/{type}
 * Body : { montant, date_paiement, mode_paiement?, note? }
 *
 * Le montant saisi ne peut pas dépasser le montant restant du versement.
 * Après chaque paiement, les statuts versement + commission sont recalculés.
 */
class VersementCommissionStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id, string $type)
    {
        if (! in_array($type, ['livreur', 'proprietaire'])) {
            return $this->notFoundResponse('Type invalide. Valeurs acceptées : livreur, proprietaire');
        }

        $commission = CommissionVente::find($id);

        if (! $commission) {
            return $this->notFoundResponse('Commission non trouvée');
        }

        $statutsEligibles = [
            StatutCommissionVente::IMPAYEE->value,
            StatutCommissionVente::PARTIELLE->value,
        ];

        if (! in_array($commission->statut->value, $statutsEligibles)) {
            return $this->errorResponse(
                'La commission n\'est pas éligible au versement (statut : ' . $commission->statut->value . ').',
                null,
                422
            );
        }

        $versement = VersementCommission::where('commission_vente_id', $id)
            ->where('beneficiaire_type', $type)
            ->first();

        if (! $versement) {
            return $this->notFoundResponse("Aucun versement de type '{$type}' pour cette commission.");
        }

        if ($versement->statut === StatutVersementCommission::EFFECTUE) {
            return $this->errorResponse('Ce versement est déjà totalement effectué.', null, 422);
        }

        // Calcul du montant restant avant validation
        $dejaVerse  = (float) $versement->paiements()->sum('montant');
        $restant    = max(0, (float) $versement->montant_attendu - $dejaVerse);

        $validated = $request->validate([
            'montant'        => [
                'required', 'numeric', 'min:1',
                function ($attribute, $value, $fail) use ($restant) {
                    if ((float) $value > $restant) {
                        $fail("Le montant ({$value}) dépasse le restant dû ({$restant}).");
                    }
                },
            ],
            'date_paiement'  => 'required|date',
            'mode_paiement'  => 'nullable|string|max:50',
            'note'           => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($versement, $commission, $validated) {
            // Créer le paiement
            PaiementVersementCommission::create([
                'versement_commission_id' => $versement->id,
                'montant'                 => $validated['montant'],
                'date_paiement'           => $validated['date_paiement'],
                'mode_paiement'           => $validated['mode_paiement'] ?? 'especes',
                'note'                    => $validated['note'] ?? null,
                'verse_par'               => auth()->id(),
            ]);

            // Recalculer statut + montant_verse du versement
            $versement->recalculStatut();

            // Recalculer statut de la commission
            $commission->recalculStatut();

            return $this->createdResponse(
                $versement->fresh(['paiements', 'commission.versements']),
                'Paiement enregistré avec succès'
            );
        });
    }
}

