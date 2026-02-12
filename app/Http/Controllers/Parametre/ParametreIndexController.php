<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Parametre;
use Illuminate\Http\Request;

class ParametreIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $query = Parametre::query();

            // Filtrer par groupe
            if ($request->has('groupe')) {
                $query->where('groupe', $request->groupe);
            }

            $parametres = $query->orderBy('groupe')->orderBy('cle')->get();

            // Transformer pour inclure la valeur castée
            $parametres = $parametres->map(function ($parametre) {
                return [
                    'id' => $parametre->id,
                    'cle' => $parametre->cle,
                    'valeur' => $parametre->valeur_castee,
                    'valeur_brute' => $parametre->valeur,
                    'type' => $parametre->type,
                    'groupe' => $parametre->groupe,
                    'groupe_label' => $parametre->groupe_label,
                    'description' => $parametre->description,
                ];
            });

            return $this->successResponse([
                'parametres' => $parametres,
                'groupes' => Parametre::GROUPES,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des paramètres', $e->getMessage());
        }
    }
}
