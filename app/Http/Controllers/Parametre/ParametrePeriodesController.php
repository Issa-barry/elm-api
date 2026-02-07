<?php

namespace App\Http\Controllers\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Parametre;
use Illuminate\Http\Request;

class ParametrePeriodesController extends Controller
{
    use ApiResponse;

    /**
     * Récupérer les périodes disponibles pour un mois/année donné
     */
    public function __invoke(Request $request)
    {
        try {
            $mois = $request->integer('mois', (int) now()->format('m'));
            $annee = $request->integer('annee', (int) now()->format('Y'));

            // Valider le mois et l'année
            if ($mois < 1 || $mois > 12) {
                return $this->errorResponse('Le mois doit être entre 1 et 12', null, 422);
            }

            if ($annee < 2020 || $annee > 2100) {
                return $this->errorResponse('L\'année doit être entre 2020 et 2100', null, 422);
            }

            $periode1 = Parametre::getPeriodeDates(1, $mois, $annee);
            $periode2 = Parametre::getPeriodeDates(2, $mois, $annee);

            $nomMois = $this->getNomMois($mois);

            return $this->successResponse([
                'mois' => $mois,
                'annee' => $annee,
                'mois_label' => $nomMois . ' ' . $annee,
                'periodes' => [
                    [
                        'numero' => 1,
                        'label' => "Période 1 - {$nomMois} {$annee} (1ère quinzaine)",
                        'debut' => $periode1['debut'],
                        'fin' => $periode1['fin'],
                    ],
                    [
                        'numero' => 2,
                        'label' => "Période 2 - {$nomMois} {$annee} (2ème quinzaine)",
                        'debut' => $periode2['debut'],
                        'fin' => $periode2['fin'],
                    ],
                ],
                'prix_rouleau_defaut' => Parametre::getPrixRouleauDefaut(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des périodes', $e->getMessage());
        }
    }

    private function getNomMois(int $mois): string
    {
        $moisFr = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];

        return $moisFr[$mois] ?? '';
    }
}
