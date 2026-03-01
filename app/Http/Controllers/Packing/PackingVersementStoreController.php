<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use App\Models\Versement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PackingVersementStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'montant'        => 'required|integer|min:1',
                'date_versement' => 'required|date',
                'mode_paiement'  => ['nullable', Rule::in(array_keys(Versement::MODES_PAIEMENT))],
                'notes'          => 'nullable|string|max:5000',
            ]);

            return DB::transaction(function () use ($id, $validated) {
                /** @var Packing|null $packing */
                $packing = Packing::lockForUpdate()->find($id);

                if (!$packing) {
                    return $this->notFoundResponse('Packing non trouve');
                }

                if ($packing->statut === PackingStatut::ANNULEE) {
                    return $this->errorResponse(
                        'Impossible d\'ajouter un versement sur un packing annule',
                        null,
                        422
                    );
                }

                $montantVerse  = (int) $packing->versements()->sum('montant');
                $montantRestant = max(0, $packing->montant - $montantVerse);

                if ($montantRestant <= 0) {
                    return $this->errorResponse('Ce packing est deja entierement paye', null, 422);
                }

                if ($validated['montant'] > $montantRestant) {
                    return $this->errorResponse(
                        "Le montant ({$validated['montant']}) depasse le restant du ({$montantRestant})",
                        null,
                        422
                    );
                }

                $versement = Versement::create([
                    'packing_id'     => $packing->id,
                    'montant'        => $validated['montant'],
                    'date_versement' => $validated['date_versement'],
                    'mode_paiement'  => $validated['mode_paiement'] ?? Versement::MODE_ESPECES,
                    'notes'          => $validated['notes'] ?? null,
                ]);

                $packing->mettreAJourStatut();
                $packing->refresh()->load(['prestataire', 'versements']);

                return $this->createdResponse([
                    'versement' => $versement,
                    'packing'   => $packing,
                ], 'Versement enregistre avec succes');
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Les donnees fournies sont invalides.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'enregistrement du versement', $e->getMessage());
        }
    }
}
