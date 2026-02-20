<?php

namespace Database\Factories;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\UsineType;
use App\Models\Produit;
use App\Models\Usine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produit>
 */
class ProduitFactory extends Factory
{
    protected $model = Produit::class;

    public function definition(): array
    {
        return [
            'nom'                => fake()->words(3, true),
            'code'               => fake()->unique()->numerify('############'), // 12 chiffres
            'type'               => ProduitType::MATERIEL->value,
            'statut'             => ProduitStatut::ACTIF->value,
            'prix_achat'         => fake()->numberBetween(100, 10000),
            'qte_stock'          => fake()->numberBetween(1, 100),
            'seuil_alerte_stock' => null,
            'is_critique'        => false,
            'usine_id'           => fn () => Usine::withoutGlobalScopes()->firstOrCreate(
                ['code' => 'TEST-DEFAULT'],
                ['nom' => 'Usine Test Default', 'type' => UsineType::USINE->value, 'statut' => 'active']
            )->id,
        ];
    }

    public function critique(): static
    {
        return $this->state(['is_critique' => true]);
    }

    public function withSeuil(int $seuil): static
    {
        return $this->state(['seuil_alerte_stock' => $seuil]);
    }

    public function withStock(int $stock): static
    {
        return $this->state(['qte_stock' => $stock]);
    }
}
