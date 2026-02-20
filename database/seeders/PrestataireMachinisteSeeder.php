<?php

namespace Database\Seeders;

use App\Enums\PrestataireType;
use App\Models\Prestataire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PrestataireMachinisteSeeder extends Seeder
{
    public function run(): void
    {
        $machinistes = [
            [
                'nom'              => 'SY',
                'prenom'           => 'Aly',
                'specialite'       => 'Gestion de packing',
                'phone'            => '622000001',
                'email'            => null,
                'ville'            => 'Conakry',
                'quartier'         => 'Matoto',
                'tarif_horaire'    => 50000,
                'notes'            => 'Machiniste principal, expérience 10 ans.',
            ],
            [
                'nom'              => 'Camara',
                'prenom'           => 'Ibrahima',
                'specialite'       => 'Réglage et opération de machines industrielles',
                'phone'            => '622000002',
                'email'            => null,
                'ville'            => 'Conakry',
                'quartier'         => 'Ratoma',
                'tarif_horaire'    => 45000,
                'notes'            => null,
            ] 
        ];

        foreach ($machinistes as $data) {
            $existe = Prestataire::withTrashed()
                ->where('nom', mb_strtoupper($data['nom'], 'UTF-8'))
                ->where('prenom', mb_convert_case($data['prenom'], MB_CASE_TITLE, 'UTF-8'))
                ->where('type', PrestataireType::MACHINISTE->value)
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
                'type'            => PrestataireType::MACHINISTE,
                'specialite'      => $data['specialite'],
                'phone'           => $data['phone'],
                'email'           => $data['email'],
                'pays'            => 'Guinee',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
                'ville'           => $data['ville'],
                'quartier'        => $data['quartier'],
                'tarif_horaire'   => $data['tarif_horaire'],
                'notes'           => $data['notes'],
                'is_active'       => true,
                'reference'       => $this->generateReference(),
            ]);
        }
    }

    private function generateReference(): string
    {
        do {
            $reference = 'PREST-' . now()->format('Ymd') . '-' . Str::upper((string) Str::ulid());
        } while (Prestataire::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }
}
