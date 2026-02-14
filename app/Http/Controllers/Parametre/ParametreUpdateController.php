<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Parametre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ParametreUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id)
    {
        try {
            $parametre = Parametre::findOrFail($id);

            $validated = $request->validate([
                'valeur' => 'required',
            ]);

            $valeur = $validated['valeur'];

            switch ($parametre->type) {
                case Parametre::TYPE_INTEGER:
                    if (filter_var($valeur, FILTER_VALIDATE_INT) === false) {
                        return $this->errorResponse('La valeur doit etre un nombre entier', null, 422);
                    }

                    $intValue = (int) $valeur;

                    if ($parametre->cle === Parametre::CLE_SEUIL_STOCK_FAIBLE && $intValue < 0) {
                        return $this->errorResponse('Le seuil de stock faible ne peut pas etre negatif', null, 422);
                    }

                    $parametre->valeur = (string) $intValue;
                    break;

                case Parametre::TYPE_BOOLEAN:
                    $parametre->valeur = ($valeur === true || $valeur === '1' || $valeur === 'true') ? '1' : '0';
                    break;

                case Parametre::TYPE_JSON:
                    if (is_array($valeur)) {
                        $parametre->valeur = json_encode($valeur);
                    } else {
                        $parametre->valeur = $valeur;
                    }
                    break;

                default:
                    $parametre->valeur = (string) $valeur;
            }

            $parametre->save();

            Cache::forget("parametre_{$parametre->cle}");

            return $this->successResponse([
                'id' => $parametre->id,
                'cle' => $parametre->cle,
                'valeur' => $parametre->valeur_castee,
                'type' => $parametre->type,
                'groupe' => $parametre->groupe,
                'description' => $parametre->description,
            ], 'Parametre mis a jour avec succes');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Parametre non trouve');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Les donnees fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise a jour du parametre', $e->getMessage());
        }
    }
}