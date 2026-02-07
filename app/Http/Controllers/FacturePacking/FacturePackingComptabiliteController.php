<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FacturePacking;
use App\Models\Packing;
use App\Models\Prestataire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacturePackingComptabiliteController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $request->validate([
                'periode_debut' => 'nullable|date',
                'periode_fin' => 'nullable|date|after_or_equal:periode_debut',
                'prestataire_id' => 'nullable|exists:prestataires,id',
            ]);

            // 1. Packings non encore facturés (validés sans facture_id)
            $queryNonFactures = Packing::query()
                ->facturables();

            if ($request->has('periode_debut')) {
                $queryNonFactures->where('date_fin', '>=', $request->periode_debut);
            }
            if ($request->has('periode_fin')) {
                $queryNonFactures->where('date_fin', '<=', $request->periode_fin);
            }
            if ($request->has('prestataire_id')) {
                $queryNonFactures->where('prestataire_id', $request->prestataire_id);
            }

            $packingsNonFactures = $queryNonFactures
                ->select(
                    'prestataire_id',
                    DB::raw('COUNT(*) as nb_packings'),
                    DB::raw('SUM(montant) as montant_total'),
                    DB::raw('SUM(nb_rouleaux) as total_rouleaux')
                )
                ->groupBy('prestataire_id')
                ->get()
                ->keyBy('prestataire_id');

            // 2. Factures en cours (non payées)
            $queryFactures = FacturePacking::query()
                ->nonPayees();

            if ($request->has('prestataire_id')) {
                $queryFactures->where('prestataire_id', $request->prestataire_id);
            }

            $facturesEnCours = $queryFactures
                ->select(
                    'prestataire_id',
                    DB::raw('COUNT(*) as nb_factures'),
                    DB::raw('SUM(montant_total) as montant_facture'),
                    DB::raw('SUM(nb_packings) as nb_packings_factures')
                )
                ->groupBy('prestataire_id')
                ->get()
                ->keyBy('prestataire_id');

            // Calculer les versements par prestataire
            $versementsParPrestataire = DB::table('versements')
                ->join('facture_packings', 'versements.facture_packing_id', '=', 'facture_packings.id')
                ->whereNull('versements.deleted_at')
                ->whereNull('facture_packings.deleted_at')
                ->when($request->has('prestataire_id'), function ($q) use ($request) {
                    $q->where('facture_packings.prestataire_id', $request->prestataire_id);
                })
                ->select(
                    'facture_packings.prestataire_id',
                    DB::raw('SUM(versements.montant) as montant_verse')
                )
                ->groupBy('facture_packings.prestataire_id')
                ->get()
                ->keyBy('prestataire_id');

            // Collecter tous les prestataires concernés
            $prestataireIds = collect()
                ->merge($packingsNonFactures->keys())
                ->merge($facturesEnCours->keys())
                ->unique();

            $prestataires = Prestataire::whereIn('id', $prestataireIds)
                ->get()
                ->keyBy('id');

            // Construire le résultat par prestataire
            $result = $prestataireIds->map(function ($prestataireId) use (
                $prestataires,
                $packingsNonFactures,
                $facturesEnCours,
                $versementsParPrestataire
            ) {
                $prestataire = $prestataires->get($prestataireId);
                $nonFacture = $packingsNonFactures->get($prestataireId);
                $enCours = $facturesEnCours->get($prestataireId);
                $versements = $versementsParPrestataire->get($prestataireId);

                $montantNonFacture = (int) ($nonFacture->montant_total ?? 0);
                $montantFacture = (int) ($enCours->montant_facture ?? 0);
                $montantVerse = (int) ($versements->montant_verse ?? 0);
                $montantRestantFacture = $montantFacture - $montantVerse;
                $montantTotalDu = $montantNonFacture + $montantRestantFacture;

                return [
                    'prestataire_id' => $prestataireId,
                    'prestataire_nom' => $prestataire?->nom_complet ?? $prestataire?->raison_sociale,
                    'prestataire_phone' => $prestataire?->phone,
                    'prestataire_type' => $prestataire?->type,

                    // Packings non encore facturés
                    'nb_packings_non_factures' => (int) ($nonFacture->nb_packings ?? 0),
                    'montant_non_facture' => $montantNonFacture,

                    // Factures en cours (facturées mais pas soldées)
                    'nb_factures_en_cours' => (int) ($enCours->nb_factures ?? 0),
                    'montant_facture' => $montantFacture,
                    'montant_verse' => $montantVerse,
                    'montant_restant_facture' => $montantRestantFacture,

                    // Total dû (non facturé + restant sur factures)
                    'montant_total_du' => $montantTotalDu,
                ];
            })->filter(fn($item) => $item['montant_total_du'] > 0)
              ->sortByDesc('montant_total_du')
              ->values();

            // Totaux généraux
            $summary = [
                'periode_debut' => $request->periode_debut,
                'periode_fin' => $request->periode_fin,
                'nb_prestataires' => $result->count(),

                // Totaux non facturés
                'total_non_facture' => $result->sum('montant_non_facture'),

                // Totaux facturés
                'total_facture' => $result->sum('montant_facture'),
                'total_verse' => $result->sum('montant_verse'),
                'total_restant_facture' => $result->sum('montant_restant_facture'),

                // Total global dû
                'montant_global_du' => $result->sum('montant_total_du'),

                'prestataires' => $result,
            ];

            return $this->successResponse($summary, 'Comptabilité récupérée avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération de la comptabilité', $e->getMessage());
        }
    }
}
