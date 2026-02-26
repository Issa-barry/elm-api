<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutCommissionVente;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommissionVente;

class CommissionVenteIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        $commissions = CommissionVente::with([
            'commande',
            'vehicule',
            'livreur',
            'proprietaire',
            'versements',
        ])
            ->when(request('statut'), function ($q, $v) {
                // Accepte une valeur unique ("eligible") ou plusieurs séparées par virgule ("eligible,versee")
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
            ->when(request('date_fin'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->paginate(request('per_page', 20));

        return $this->successResponse($commissions, 'Liste des commissions de vente');
    }
}
