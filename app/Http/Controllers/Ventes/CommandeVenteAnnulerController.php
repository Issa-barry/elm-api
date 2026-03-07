<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommandeVente;
use App\Services\CommandeVenteAnnulationService;
use Illuminate\Http\Request;

class CommandeVenteAnnulerController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id, CommandeVenteAnnulationService $service)
    {
        $request->validate([
            'motif_annulation' => ['required', 'string', 'max:500'],
        ]);

        $commande = CommandeVente::with(['facture.encaissements', 'commission.versements', 'lignes'])
            ->find($id);

        if (! $commande) {
            return $this->notFoundResponse('Commande introuvable.');
        }

        try {
            $commande = $service->annuler(
                $commande,
                $request->user(),
                $request->input('motif_annulation')
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }

        return $this->successResponse($commande, 'Commande annulée avec succès.');
    }
}
