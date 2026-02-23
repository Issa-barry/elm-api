<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\EncaissementVente;

class EncaissementVenteIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        $encaissements = EncaissementVente::with('facture')
            ->when(request('facture_vente_id'), fn ($q, $id) => $q->where('facture_vente_id', $id))
            ->orderByDesc('date_encaissement')
            ->paginate(20);

        return $this->successResponse($encaissements, 'Liste des encaissements');
    }
}
