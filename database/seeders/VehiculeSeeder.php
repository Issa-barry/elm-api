<?php

namespace Database\Seeders;

use App\Enums\TypeVehicule;
use App\Enums\SiteType;
use App\Models\Livreur;
use App\Models\Proprietaire;
use App\Models\Site;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;

class VehiculeSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::withTrashed()->where('code', 'ELM-USN-01')->first()
            ?? Site::withTrashed()->where('type', SiteType::USINE->value)->first();

        if (! $site) {
            $site = Site::withTrashed()->firstOrNew([
                'code' => 'TEST-DEFAULT',
            ]);
            $site->fill([
                'nom'    => 'Usine Test Default',
                'type'   => SiteType::USINE->value,
                'statut' => 'active',
            ]);
            $site->save();
        }

        if ($site->trashed()) {
            $site->restore();
        }

        $proprietairesData = [
            [
                'nom'      => 'DIALLO',
                'prenom'   => 'Mamadou',
                'phone'    => '+224620100201',
                'email'    => 'mamadou.diallo@elm.local',
                'pays'     => 'Guinee',
                'ville'    => 'Conakry',
                'quartier' => 'Matam',
            ],
            [
                'nom'      => 'BARRY',
                'prenom'   => 'Fatou',
                'phone'    => '+224620100202',
                'email'    => 'fatou.barry@elm.local',
                'pays'     => 'Guinee',
                'ville'    => 'Conakry',
                'quartier' => 'Ratoma',
            ],
        ];

        $proprietaires = [];
        foreach ($proprietairesData as $data) {
            $proprietaire = Proprietaire::withoutGlobalScopes()
                ->withTrashed()
                ->firstOrNew([
                    'site_id' => $site->id,
                    'phone'   => $data['phone'],
                ]);

            $proprietaire->fill([
                'site_id'   => $site->id,
                'nom'       => $data['nom'],
                'prenom'    => $data['prenom'],
                'phone'     => $data['phone'],
                'email'     => $data['email'],
                'pays'      => $data['pays'],
                'ville'     => $data['ville'],
                'quartier'  => $data['quartier'],
                'is_active' => true,
            ]);
            $proprietaire->save();

            if ($proprietaire->trashed()) {
                $proprietaire->restore();
            }

            $proprietaires[] = $proprietaire->fresh();
        }

        $livreursData = [
            [
                'nom'      => 'BALDE',
                'prenom'   => 'Alpha',
                'phone'    => '+224621100201',
                'email'    => 'alpha.balde@elm.local',
                'pays'     => 'Guinee',
                'ville'    => 'Conakry',
                'quartier' => 'Lambanyi',
            ],
            [
                'nom'      => 'BAH',
                'prenom'   => 'Ousmane',
                'phone'    => '+224621100202',
                'email'    => 'ousmane.bah@elm.local',
                'pays'     => 'Guinee',
                'ville'    => 'Conakry',
                'quartier' => 'Sonfonia',
            ],
        ];

        $livreurs = [];
        foreach ($livreursData as $data) {
            $livreur = Livreur::withoutGlobalScopes()
                ->withTrashed()
                ->firstOrNew([
                    'site_id' => $site->id,
                    'phone'   => $data['phone'],
                ]);

            $livreur->fill([
                'site_id'   => $site->id,
                'nom'       => $data['nom'],
                'prenom'    => $data['prenom'],
                'phone'     => $data['phone'],
                'email'     => $data['email'],
                'pays'      => $data['pays'],
                'ville'     => $data['ville'],
                'quartier'  => $data['quartier'],
                'is_active' => true,
            ]);
            $livreur->save();

            if ($livreur->trashed()) {
                $livreur->restore();
            }

            $livreurs[] = $livreur->fresh();
        }

        $vehiculesData = [
            [
                'immatriculation'   => 'GN-1001-AA',
                'nom_vehicule'      => 'Camion Alpha',
                'marque'            => 'Iveco',
                'modele'            => 'Eurocargo',
                'type_vehicule'     => TypeVehicule::CAMION->value,
                'capacite_packs'    => 300,
                'proprietaire_idx'  => 0,
                'livreur_idx'       => 0,
            ],
            [
                'immatriculation'   => 'GN-1002-BB',
                'nom_vehicule'      => 'Vanne Beta',
                'marque'            => 'Toyota',
                'modele'            => 'Hiace',
                'type_vehicule'     => TypeVehicule::VANNE->value,
                'capacite_packs'    => 150,
                'proprietaire_idx'  => 1,
                'livreur_idx'       => 1,
            ],
            [
                'immatriculation'   => 'GN-1003-CC',
                'nom_vehicule'      => 'Tricycle Gamma',
                'marque'            => 'TVS',
                'modele'            => 'King',
                'type_vehicule'     => TypeVehicule::TRICYCLE->value,
                'capacite_packs'    => 70,
                'proprietaire_idx'  => 0,
                'livreur_idx'       => 1,
            ],
            [
                'immatriculation'   => 'GN-1004-DD',
                'nom_vehicule'      => 'Pick Up Delta',
                'marque'            => 'Isuzu',
                'modele'            => 'D-Max',
                'type_vehicule'     => TypeVehicule::PICK_UP->value,
                'capacite_packs'    => 120,
                'proprietaire_idx'  => 1,
                'livreur_idx'       => 0,
            ],
            [
                'immatriculation'   => 'GN-1005-EE',
                'nom_vehicule'      => 'Moto Epsilon',
                'marque'            => 'Yamaha',
                'modele'            => 'YBR',
                'type_vehicule'     => TypeVehicule::MOTO->value,
                'capacite_packs'    => 20,
                'proprietaire_idx'  => 0,
                'livreur_idx'       => 1,
            ],
        ];

        foreach ($vehiculesData as $data) {
            $vehicule = Vehicule::withoutGlobalScopes()
                ->withTrashed()
                ->firstOrNew([
                    'site_id'         => $site->id,
                    'immatriculation' => $data['immatriculation'],
                ]);

            $vehicule->fill([
                'site_id'                  => $site->id,
                'nom_vehicule'             => $data['nom_vehicule'],
                'marque'                   => $data['marque'],
                'modele'                   => $data['modele'],
                'immatriculation'          => $data['immatriculation'],
                'type_vehicule'            => $data['type_vehicule'],
                'capacite_packs'           => $data['capacite_packs'],
                'proprietaire_id'          => $proprietaires[$data['proprietaire_idx']]->id,
                'livreur_principal_id'     => $livreurs[$data['livreur_idx']]->id,
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
