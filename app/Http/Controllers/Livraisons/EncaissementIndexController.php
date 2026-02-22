<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\EncaissementLivraison;
use Illuminate\Http\Request;

class EncaissementIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $encaissements = EncaissementLivraison::with(['facture'])
            ->when($request->input('facture_livraison_id'), fn ($q, $fid) => $q->where('facture_livraison_id', $fid))
            ->orderByDesc('date_encaissement')
            ->paginate(20);

        return $this->successResponse($encaissements, 'Liste des encaissements');
    }
}
