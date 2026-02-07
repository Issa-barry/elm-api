<?php

namespace App\Http\Controllers\PaiementPacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PaiementPacking;
use Illuminate\Support\Facades\DB;

class PaiementPackingDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $paiement = PaiementPacking::findOrFail($id);

                // Annuler le paiement (libère les packings)
                $paiement->annuler();

                return $this->successResponse(null, 'Paiement annulé avec succès');
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Paiement non trouvé');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'annulation du paiement', $e->getMessage());
        }
    }
}
