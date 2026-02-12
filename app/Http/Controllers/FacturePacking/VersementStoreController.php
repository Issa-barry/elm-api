<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FacturePacking;
use App\Models\Versement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VersementStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $factureId)
    {
        try {
            $facture = FacturePacking::findOrFail($factureId);

            $validated = $request->validate([
                'montant' => 'required|integer|min:1',
                'date_versement' => 'required|date',
                'mode_paiement' => ['nullable', Rule::in(array_keys(Versement::MODES_PAIEMENT))],
                'notes' => 'nullable|string|max:5000',
            ]);

            // Vérifier que la facture n'est pas annulée
            if ($facture->statut === FacturePacking::STATUT_ANNULEE) {
                return $this->errorResponse(
                    'Impossible d\'ajouter un versement sur une facture annulée',
                    null,
                    422
                );
            }

            // Vérifier que le montant ne dépasse pas le restant dû
            $montantRestant = $facture->montant_restant;
            if ($validated['montant'] > $montantRestant) {
                return $this->errorResponse(
                    "Le montant du versement ({$validated['montant']}) dépasse le montant restant dû ({$montantRestant})",
                    null,
                    422
                );
            }

            return DB::transaction(function () use ($facture, $validated) {
                $versement = Versement::create([
                    'facture_packing_id' => $facture->id,
                    'montant' => $validated['montant'],
                    'date_versement' => $validated['date_versement'],
                    'mode_paiement' => $validated['mode_paiement'] ?? Versement::MODE_ESPECES,
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Mettre à jour le statut de la facture
                $facture->mettreAJourStatut();

                // Recharger la facture avec les versements
                $facture->load(['prestataire', 'packings', 'versements']);

                return $this->createdResponse([
                    'versement' => $versement,
                    'facture' => $facture,
                ], 'Versement enregistré avec succès');
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Facture non trouvée');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'enregistrement du versement', $e->getMessage());
        }
    }
}
