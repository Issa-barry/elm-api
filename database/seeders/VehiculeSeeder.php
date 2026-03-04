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
        $usine = Site::withTrashed()->where('nom', 'Usine de kaka')->first()
            ?? Site::withTrashed()->where('type', SiteType::USINE->value)->first();

        if (! $usine) {
            $usine = Site::withTrashed()->firstOrNew([
                'code' => 'TEST-DEFAULT',
            ]);
            $usine->fill([
                'nom' => 'Usine Test Default',
                'type' => SiteType::USINE->value,
                'statut' => 'active',
            ]);
            $usine->save();
        }
        if ($usine->trashed()) {
            $usine->restore();
        }

        $proprietaire = Proprietaire::withoutGlobalScopes()
            ->withTrashed()
            ->firstOrNew([
                'site_id' => $usine->id,
                'phone'    => '+224620100200',
            ]);
        $proprietaire->fill([
            'site_id' => $usine->id,
            'nom'      => 'DIALLO',
            'prenom'   => 'Mamadou',
            'email'    => null,
            'pays'     => 'Guinee',
            'ville'    => 'Conakry',
            'quartier' => 'Matam',
            'is_active' => true,
        ]);
        $proprietaire->save();
        if ($proprietaire->trashed()) {
            $proprietaire->restore();
        }

        $livreur = Livreur::withoutGlobalScopes()
            ->withTrashed()
            ->firstOrNew([
                'site_id' => $usine->id,
                'phone'    => '+224621100200',
            ]);
        $livreur->fill([
            'site_id' => $usine->id,
            'nom'      => 'BALDE',
            'prenom'   => 'Alpha',
            'email'    => null,
            'pays'     => 'Guinee',
            'ville'    => 'Conakry',
            'quartier' => 'Ratoma',
            'is_active' => true,
        ]);
        $livreur->save();
        if ($livreur->trashed()) {
            $livreur->restore();
        }

        $vehicule = Vehicule::withoutGlobalScopes()
            ->withTrashed()
            ->firstOrNew([
                'site_id' => $usine->id,
                'immatriculation' => 'RC-802-WK',
            ]);
        $vehicule->fill([
            'nom_vehicule' => 'Camion Nen dow',
            'marque' => 'Iveco',
            'modele' => 'Eurocargo',
            'type_vehicule' => TypeVehicule::CAMION->value,
            'capacite_packs' => 300,
            'proprietaire_id' => $proprietaire->id,
            'livreur_principal_id' => $livreur->id,
            'pris_en_charge_par_usine' => false,
            'taux_commission_livreur' => 60.00,
            'commission_active' => true,
            'photo_path' => 'vehicules/default.jpg',
            'is_active' => true,
        ]);
        $vehicule->save();
        if ($vehicule->trashed()) {
            $vehicule->restore();
        }
    }
}
