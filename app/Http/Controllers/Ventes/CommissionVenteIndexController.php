<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutCommissionVente;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommissionVente;
use App\Models\VersementCommission;
use Illuminate\Database\Eloquent\Builder;

class CommissionVenteIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        $query = CommissionVente::query()
            ->when(request('statut'), function ($q, $v) {
                $valeurs = collect(explode(',', $v))
                    ->map(fn ($s) => StatutCommissionVente::tryFrom(trim($s)))
                    ->filter()
                    ->map(fn ($e) => $e->value)
                    ->values()
                    ->all();

                if (!empty($valeurs)) {
                    count($valeurs) === 1
                        ? $q->where('statut', $valeurs[0])
                        : $q->whereIn('statut', $valeurs);
                }
            })
            ->when(request('vehicule_id'), fn ($q, $v) => $q->where('vehicule_id', (int) $v))
            ->when(request('livreur_id'), fn ($q, $v) => $q->where('livreur_id', (int) $v))
            ->when(request('date_debut'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when(request('date_fin'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        // Totaux calculés sur toutes les commissions filtrées (pas seulement la page courante)
        $ids            = (clone $query)->pluck('id');
        $montantTotal   = (clone $query)->sum('montant_commission_total');
        $montantVerse   = VersementCommission::whereIn('commission_vente_id', $ids)->sum('montant_verse');
        $montantRestant = max(0, (float) $montantTotal - (float) $montantVerse);

        $totaux = [
            'montant_total'   => (float) $montantTotal,
            'montant_verse'   => (float) $montantVerse,
            'montant_restant' => $montantRestant,
            'nb_commissions'  => $ids->count(),
        ];

        $commissions = $query->with([
            'commande',
            'vehicule',
            'livreur',
            'proprietaire',
            'versements',
        ])
            ->orderByDesc('created_at')
            ->paginate(request('per_page', 20));

        return $this->successResponse(
            ['totaux' => $totaux, 'commissions' => $commissions],
            'Liste des commissions de vente'
        );
    }
}
