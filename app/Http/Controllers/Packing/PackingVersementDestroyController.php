<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use App\Models\Versement;
use Illuminate\Support\Facades\DB;

class PackingVersementDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id, int $versementId)
    {
        try {
            return DB::transaction(function () use ($id, $versementId) {
                /** @var Packing|null $packing */
                $packing = Packing::lockForUpdate()->find($id);

                if (!$packing) {
                    return $this->notFoundResponse('Packing non trouve');
                }

                $versement = Versement::where('packing_id', $id)
                    ->where('id', $versementId)
                    ->first();

                if (!$versement) {
                    return $this->notFoundResponse('Versement non trouve');
                }

                $versement->delete();

                $packing->mettreAJourStatut();
                $packing->refresh()->load(['prestataire', 'versements']);

                return $this->successResponse([
                    'packing' => $packing,
                ], 'Versement supprime avec succes');
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du versement', $e->getMessage());
        }
    }
}
