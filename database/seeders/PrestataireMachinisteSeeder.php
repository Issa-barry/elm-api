<?php

namespace Database\Seeders;

use App\Enums\PrestataireType;
use App\Models\Prestataire;
use App\Models\Usine;
use Illuminate\Database\Seeder;

class PrestataireMachinisteSeeder extends Seeder
{
    /**
     * Prestataires par usine (nom usine => liste).
     * Dans un seeder il n'y a pas de contexte HTTP : HasUsineScope ne peut pas
     * auto-remplir usine_id. On rattache explicitement chaque prestataire à son usine.
     */
    private array $prestatairesParUsine = [
        'Usine de kaka' => [
            [
                'nom'            => 'SY',
                'prenom'         => 'Aly',
                'raison_sociale' => null,
                'type'           => PrestataireType::MACHINISTE,
                'specialite'     => 'Gestion de packing',
                'phone'          => '622000001',
                'ville'          => 'Conakry',
                'quartier'       => 'Matoto',
                'tarif_horaire'  => 50000,
                'notes'          => 'Machiniste principal, expérience 10 ans.',
            ],
            [
                'nom'            => 'CAMARA',
                'prenom'         => 'Ibrahima',
                'raison_sociale' => null,
                'type'           => PrestataireType::MACHINISTE,
                'specialite'     => 'Réglage et opération de machines industrielles',
                'phone'          => '622000002',
                'ville'          => 'Conakry',
                'quartier'       => 'Ratoma',
                'tarif_horaire'  => 45000,
                'notes'          => null,
            ],
            [
                'nom'            => 'DIALLO',
                'prenom'         => 'Sekou',
                'raison_sociale' => null,
                'type'           => PrestataireType::MECANICIEN,
                'specialite'     => 'Maintenance des machines de packing',
                'phone'          => '622000005',
                'ville'          => 'Conakry',
                'quartier'       => 'Wanindara',
                'tarif_horaire'  => 40000,
                'notes'          => null,
            ],
            [
                'nom'            => 'TOPAZE',
                'prenom'         => 'Cie',
                'raison_sociale' => 'TOPAZE',
                'type'           => PrestataireType::FOURNISSEUR,
                'specialite'     => 'Fourniture de matières premières',
                'phone'          => '622000010',
                'ville'          => 'Conakry',
                'quartier'       => null,
                'tarif_horaire'  => null,
                'notes'          => null,
            ],
        ],
        'Usine de Matoto' => [
            [
                'nom'            => 'BAH',
                'prenom'         => 'Mamadou',
                'raison_sociale' => null,
                'type'           => PrestataireType::CONSULTANT,
                'specialite'     => 'Conseil en gestion industrielle',
                'phone'          => '622000003',
                'ville'          => 'Conakry',
                'quartier'       => 'Kaloum',
                'tarif_horaire'  => 80000,
                'notes'          => null,
            ],
            [
                'nom'            => 'TOPAZE',
                'prenom'         => 'Cie',
                'raison_sociale' => 'TOPAZE',
                'type'           => PrestataireType::FOURNISSEUR,
                'specialite'     => 'Fourniture de matières premières',
                'phone'          => '622000010',
                'ville'          => 'Conakry',
                'quartier'       => null,
                'tarif_horaire'  => null,
                'notes'          => null,
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->prestatairesParUsine as $usineNom => $prestataires) {
            $usine = Usine::where('nom', $usineNom)->first();

            if (!$usine) {
                $this->command->warn("PrestataireMachinisteSeeder : usine [{$usineNom}] introuvable, ignorée.");
                continue;
            }

            foreach ($prestataires as $data) {
                $nomNormalise    = mb_strtoupper($data['nom'], 'UTF-8');
                $prenomNormalise = mb_convert_case($data['prenom'], MB_CASE_TITLE, 'UTF-8');

                $existe = Prestataire::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('nom', $nomNormalise)
                    ->where('prenom', $prenomNormalise)
                    ->where('type', $data['type']->value)
                    ->where('usine_id', $usine->id)
                    ->first();

                if ($existe) {
                    if ($existe->trashed()) {
                        $existe->restore();
                    }
                    continue;
                }

                Prestataire::create([
                    'nom'             => $data['nom'],
                    'prenom'          => $data['prenom'],
                    'raison_sociale'  => $data['raison_sociale'],
                    'type'            => $data['type'],
                    'specialite'      => $data['specialite'],
                    'phone'           => $data['phone'],
                    'email'           => $data['email'] ?? null,
                    'pays'            => 'Guinee',
                    'code_pays'       => 'GN',
                    'code_phone_pays' => '+224',
                    'ville'           => $data['ville'],
                    'quartier'        => $data['quartier'],
                    'tarif_horaire'   => $data['tarif_horaire'],
                    'notes'           => $data['notes'],
                    'is_active'       => true,
                    'usine_id'        => $usine->id,
                    'reference'       => Prestataire::generateReference(),
                ]);
            }

            $this->command->info("PrestataireMachinisteSeeder : " . count($prestataires) . " prestataire(s) traité(s) pour [{$usineNom}].");
        }
    }
}
