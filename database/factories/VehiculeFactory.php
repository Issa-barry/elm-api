<?php

namespace Database\Factories;

use App\Enums\ModeCommission;
use App\Enums\TypeVehicule;
use App\Enums\UsineType;
use App\Models\Livreur;
use App\Models\Proprietaire;
use App\Models\Usine;
use App\Models\Vehicule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicule>
 */
class VehiculeFactory extends Factory
{
    protected $model = Vehicule::class;

    public function definition(): array
    {
        return [
            'usine_id'                  => fn () => Usine::withoutGlobalScopes()->firstOrCreate(
                ['code' => 'TEST-DEFAULT'],
                ['nom' => 'Usine Test Default', 'type' => UsineType::USINE->value, 'statut' => 'active']
            )->id,
            'nom_vehicule'              => fake()->words(2, true),
            'immatriculation'           => strtoupper(fake()->unique()->bothify('??-####-?')),
            'type_vehicule'             => TypeVehicule::CAMION->value,
            'capacite_packs'            => fake()->numberBetween(50, 500),
            'proprietaire_id'           => Proprietaire::factory(),
            'livreur_principal_id'      => null,
            'pris_en_charge_par_usine'  => false,
            'mode_commission'           => ModeCommission::FORFAIT->value,
            'valeur_commission'         => fake()->randomFloat(2, 100, 1000),
            'pourcentage_proprietaire'  => 60.00,
            'pourcentage_livreur'       => 40.00,
            'photo_path'                => 'vehicules/default.jpg',
            'is_active'                 => true,
        ];
    }

    public function withLivreur(int $livreurId): static
    {
        return $this->state(['livreur_principal_id' => $livreurId]);
    }

    public function pourcentage(float $proprio, float $livreur): static
    {
        return $this->state([
            'pourcentage_proprietaire' => $proprio,
            'pourcentage_livreur'      => $livreur,
        ]);
    }
}
