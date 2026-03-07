<?php

namespace Database\Seeders;

use App\Enums\TypeVehicule;
use App\Models\Livreur;
use App\Models\Proprietaire;
use App\Models\Site;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;

class VehiculeSeeder extends Seeder
{
    // Données par site : index => suffixe utilisé pour phone/immat/email
    private array $sitesConfig = [
        [
            'code'   => 'ELM-SIEGE',
            'suffix' => '01',
            'immat'  => 'AA',
        ],
        [
            'code'   => 'ELM-USN-01',
            'suffix' => '02',
            'immat'  => 'BB',
        ],
    ];

    private array $vehiculesTemplate = [
        [
            'nom_vehicule'   => 'Camion',
            'marque'         => 'Iveco',
            'modele'         => 'Eurocargo',
            'type_vehicule'  => TypeVehicule::CAMION,
            'capacite_packs' => 300,
            'prop_idx'       => 0,
            'livr_idx'       => 0,
            'immat_num'      => '1001',
        ],
        [
            'nom_vehicule'   => 'Tricycle',
            'marque'         => 'TVS',
            'modele'         => 'King',
            'type_vehicule'  => TypeVehicule::TRICYCLE,
            'capacite_packs' => 70,
            'prop_idx'       => 1,
            'livr_idx'       => 1,
            'immat_num'      => '1002',
        ],
        [
            'nom_vehicule'   => 'Vanne',
            'marque'         => 'Toyota',
            'modele'         => 'Hiace',
            'type_vehicule'  => TypeVehicule::VANNE,
            'capacite_packs' => 150,
            'prop_idx'       => 0,
            'livr_idx'       => 1,
            'immat_num'      => '1003',
        ],
    ];

    public function run(): void
    {
        foreach ($this->sitesConfig as $cfg) {
            $site = Site::withTrashed()->where('code', $cfg['code'])->first();

            if (! $site) {
                continue;
            }

            if ($site->trashed()) {
                $site->restore();
            }

            $s = $cfg['suffix'];
            $i = $cfg['immat'];

            $proprietaires = $this->upsertProprietaires($site->id, $s);
            $livreurs      = $this->upsertLivreurs($site->id, $s);
            $this->upsertVehicules($site->id, $i, $proprietaires, $livreurs);
        }
    }

    private function upsertProprietaires(int $siteId, string $s): array
    {
        $data = [
            [
                'nom'      => 'DIALLO',
                'prenom'   => 'Mamadou',
                'phone'    => "+2246201{$s}201",
                'email'    => "mamadou.diallo.{$s}@elm.local",
                'pays'     => 'Guinee',
                'ville'    => 'Conakry',
                'quartier' => 'Matam',
            ],
            [
                'nom'      => 'BARRY',
                'prenom'   => 'Fatou',
                'phone'    => "+2246201{$s}202",
                'email'    => "fatou.barry.{$s}@elm.local",
                'pays'     => 'Guinee',
                'ville'    => 'Conakry',
                'quartier' => 'Ratoma',
            ],
        ];

        $result = [];
        foreach ($data as $row) {
            $prop = Proprietaire::withoutGlobalScopes()
                ->withTrashed()
                ->firstOrNew(['site_id' => $siteId, 'phone' => $row['phone']]);

            $prop->fill(array_merge($row, ['site_id' => $siteId, 'is_active' => true]));
            $prop->save();

            if ($prop->trashed()) {
                $prop->restore();
            }

            $result[] = $prop->fresh();
        }

        return $result;
    }

    private function upsertLivreurs(int $siteId, string $s): array
    {
        $data = [
            [
                'nom'      => 'BALDE',
                'prenom'   => 'Alpha',
                'phone'    => "+2246211{$s}201",
                'email'    => "alpha.balde.{$s}@elm.local",
                'pays'     => 'Guinee',
                'ville'    => 'Conakry',
                'quartier' => 'Lambanyi',
            ],
            [
                'nom'      => 'BAH',
                'prenom'   => 'Ousmane',
                'phone'    => "+2246211{$s}202",
                'email'    => "ousmane.bah.{$s}@elm.local",
                'pays'     => 'Guinee',
                'ville'    => 'Conakry',
                'quartier' => 'Sonfonia',
            ],
        ];

        $result = [];
        foreach ($data as $row) {
            $livreur = Livreur::withoutGlobalScopes()
                ->withTrashed()
                ->firstOrNew(['site_id' => $siteId, 'phone' => $row['phone']]);

            $livreur->fill(array_merge($row, ['site_id' => $siteId, 'is_active' => true]));
            $livreur->save();

            if ($livreur->trashed()) {
                $livreur->restore();
            }

            $result[] = $livreur->fresh();
        }

        return $result;
    }

    private function upsertVehicules(int $siteId, string $immatSuffix, array $proprietaires, array $livreurs): void
    {
        foreach ($this->vehiculesTemplate as $tpl) {
            $immat   = "GN-{$tpl['immat_num']}-{$immatSuffix}";
            $type    = $tpl['type_vehicule'];
            $libelle = $tpl['nom_vehicule'];

            $vehicule = Vehicule::withoutGlobalScopes()
                ->withTrashed()
                ->firstOrNew(['site_id' => $siteId, 'immatriculation' => $immat]);

            $vehicule->fill([
                'site_id'                  => $siteId,
                'nom_vehicule'             => $libelle,
                'marque'                   => $tpl['marque'],
                'modele'                   => $tpl['modele'],
                'immatriculation'          => $immat,
                'type_vehicule'            => $type->value,
                'capacite_packs'           => $tpl['capacite_packs'],
                'proprietaire_id'          => $proprietaires[$tpl['prop_idx']]->id,
                'livreur_principal_id'     => $livreurs[$tpl['livr_idx']]->id,
                'pris_en_charge_par_usine' => false,
                'taux_commission_livreur'  => 60.00,
                'commission_active'        => true,
                'photo_path'               => 'vehicules/default.jpg',
                'is_active'                => true,
            ]);
            $vehicule->save();

            if ($vehicule->trashed()) {
                $vehicule->restore();
            }
        }
    }
}
