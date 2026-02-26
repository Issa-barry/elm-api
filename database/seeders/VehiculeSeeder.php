<?php

namespace Database\Seeders;

use App\Enums\TypeVehicule;
use App\Enums\UsineType;
use App\Models\Livreur;
use App\Models\Proprietaire;
use App\Models\Usine;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;

class VehiculeSeeder extends Seeder
{
    public function run(): void
    {
        $usine = Usine::withTrashed()->where('code', 'ELM-USN-01')->first()
            ?? Usine::withTrashed()->where('type', UsineType::USINE->value)->first();

        if (! $usine) {
            $usine = Usine::withTrashed()->firstOrNew([
                'code' => 'TEST-DEFAULT',
            ]);
            $usine->fill([
                'nom' => 'Usine Test Default',
                'type' => UsineType::USINE->value,
                'statut' => 'active',
            ]);
            $usine->save();
        }
        if ($usine->trashed()) {
            $usine->restore();
        }

        $proprietaire = Proprietaire::withTrashed()->firstOrNew([
            'phone' => '+224620100200',
        ]);
        $proprietaire->fill([
            'nom' => 'DIALLO',
            'prenom' => 'Mamadou',
            'email' => null,
            'pays' => 'Guinee',
            'ville' => 'Conakry',
            'quartier' => 'Matam',
            'is_active' => true,
        ]);
        $proprietaire->save();
        if ($proprietaire->trashed()) {
            $proprietaire->restore();
        }

        $livreur = Livreur::withTrashed()->firstOrNew([
            'phone' => '+224621100200',
        ]);
        $livreur->fill([
            'nom' => 'BALDE',
            'prenom' => 'Alpha',
            'email' => null,
            'pays' => 'Guinee',
            'ville' => 'Conakry',
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
                'usine_id' => $usine->id,
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
