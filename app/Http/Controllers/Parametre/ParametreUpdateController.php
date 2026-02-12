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

            // Valider selon le type
            $valeur = $validated['valeur'];

            switch ($parametre->type) {
                case Parametre::TYPE_INTEGER:
                    if (!is_numeric($valeur)) {
                        return $this->errorResponse('La valeur doit être un nombre entier', null, 422);
                    }
                    $parametre->valeur = (string) (int) $valeur;
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

            // Invalider le cache
            Cache::forget("parametre_{$parametre->cle}");

            return $this->successResponse([
                'id' => $parametre->id,
                'cle' => $parametre->cle,
                'valeur' => $parametre->valeur_castee,
                'type' => $parametre->type,
                'groupe' => $parametre->groupe,
                'description' => $parametre->description,
            ], 'Paramètre mis à jour avec succès');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Paramètre non trouvé');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du paramètre', $e->getMessage());
        }
    }
}
