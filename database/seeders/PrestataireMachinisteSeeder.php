<?php

namespace Database\Seeders;

use App\Enums\PrestataireType;
use App\Models\Prestataire;
use App\Models\Site;
use Illuminate\Database\Seeder;

class PrestataireMachinisteSeeder extends Seeder
{
    // Prestataires communs à tous les sites (phone suffixé par site pour unicité)
    private array $machinisteTemplate = [
        [
            'nom'           => 'SY',
            'prenom'        => 'Aly',
            'type'          => PrestataireType::MACHINISTE,
            'specialite'    => 'Gestion de packing',
            'phone_base'    => '6220001',
            'ville'         => 'Conakry',
            'quartier'      => 'Matoto',
            'tarif_horaire' => 50000,
            'notes'         => 'Machiniste principal, expérience 10 ans.',
        ],
        [
            'nom'           => 'CAMARA',
            'prenom'        => 'Ibrahima',
            'type'          => PrestataireType::MACHINISTE,
            'specialite'    => 'Réglage et opération de machines industrielles',
            'phone_base'    => '6220002',
            'ville'         => 'Conakry',
            'quartier'      => 'Ratoma',
            'tarif_horaire' => 45000,
            'notes'         => null,
        ],
        [
            'nom'           => 'DIALLO',
            'prenom'        => 'Sekou',
            'type'          => PrestataireType::MACHINISTE,
            'specialite'    => 'Maintenance des machines de packing',
            'phone_base'    => '6220003',
            'ville'         => 'Conakry',
            'quartier'      => 'Wanindara',
            'tarif_horaire' => 40000,
            'notes'         => null,
        ],
    ];

    // Prestataires spécifiques par site (code => liste)
    private array $specifiquesParSite = [
        'ELM-SIEGE' => [
            [
                'nom'           => 'BAH',
                'prenom'        => 'Mamadou',
                'type'          => PrestataireType::CONSULTANT,
                'specialite'    => 'Conseil en gestion industrielle',
                'phone_base'    => '6220010',
                'ville'         => 'Conakry',
                'quartier'      => 'Kaloum',
                'tarif_horaire' => 80000,
                'notes'         => null,
            ],
            [
                'nom'            => 'TOPAZE',
                'prenom'         => 'Cie',
                'raison_sociale' => 'TOPAZE',
                'type'           => PrestataireType::FOURNISSEUR,
                'specialite'     => 'Fourniture de matières premières',
                'phone_base'     => '6220020',
                'ville'          => 'Conakry',
                'quartier'       => null,
                'tarif_horaire'  => null,
                'notes'          => null,
            ],
        ],
        'ELM-USN-01' => [
            [
                'nom'            => 'TOPAZE',
                'prenom'         => 'Cie',
                'raison_sociale' => 'TOPAZE',
                'type'           => PrestataireType::FOURNISSEUR,
                'specialite'     => 'Fourniture de matières premières',
                'phone_base'     => '6220020',
                'ville'          => 'Conakry',
                'quartier'       => null,
                'tarif_horaire'  => null,
                'notes'          => null,
            ],
        ],
    ];

    // Suffixe par code site pour différencier les phones
    private array $siteSuffixes = [
        'ELM-SIEGE'  => '01',
        'ELM-USN-01' => '02',
    ];

    public function run(): void
    {
        $sites = Site::withTrashed()
            ->whereIn('code', array_keys($this->siteSuffixes))
            ->get()
            ->keyBy('code');

        foreach ($sites as $code => $site) {
            if ($site->trashed()) {
                $site->restore();
            }

            $suffix = $this->siteSuffixes[$code];

            // Machinistes communs
            foreach ($this->machinisteTemplate as $tpl) {
                $this->upsert($site->id, array_merge($tpl, [
                    'phone' => $tpl['phone_base'] . $suffix,
                ]));
            }

            // Spécifiques au site
            foreach ($this->specifiquesParSite[$code] ?? [] as $tpl) {
                $this->upsert($site->id, array_merge($tpl, [
                    'phone' => $tpl['phone_base'] . $suffix,
                ]));
            }

            $this->command->info("PrestataireMachinisteSeeder : site [{$code}] traité.");
        }
    }

    private function upsert(int $siteId, array $data): void
    {
        $nomNormalise    = mb_strtoupper($data['nom'], 'UTF-8');
        $prenomNormalise = mb_convert_case($data['prenom'], MB_CASE_TITLE, 'UTF-8');

        $existe = Prestataire::withoutGlobalScopes()
            ->withTrashed()
            ->where('nom', $nomNormalise)
            ->where('prenom', $prenomNormalise)
            ->where('type', $data['type']->value)
            ->where('site_id', $siteId)
            ->first();

        if ($existe) {
            if ($existe->trashed()) {
                $existe->restore();
            }
            return;
        }

        Prestataire::create([
            'nom'             => $data['nom'],
            'prenom'          => $data['prenom'],
            'raison_sociale'  => $data['raison_sociale'] ?? null,
            'type'            => $data['type'],
            'specialite'      => $data['specialite'],
            'phone'           => $data['phone'],
            'email'           => $data['email'] ?? null,
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => $data['ville'],
            'quartier'        => $data['quartier'] ?? null,
            'tarif_horaire'   => $data['tarif_horaire'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'is_active'       => true,
            'site_id'         => $siteId,
            'reference'       => Prestataire::generateReference(),
        ]);
    }
}
