<?php

namespace Database\Factories;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\UsineType;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Usine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produit>
 */
class ProduitFactory extends Factory
{
    protected $model = Produit::class;

    // Valeurs stock mémorisées pour afterCreating
    private int $stockQte   = 0;
    private ?int $stockSeuil = null;

    public function definition(): array
    {
        $this->stockQte   = fake()->numberBetween(1, 100);
        $this->stockSeuil = null;

        return [
            'nom'         => fake()->words(3, true),
            'code'        => fake()->unique()->numerify('############'), // 12 chiffres
            'type'        => ProduitType::MATERIEL->value,
            'statut'      => ProduitStatut::ACTIF->value,
            'prix_achat'  => fake()->numberBetween(100, 10000),
            'is_critique' => false,
            'is_global'  => false,
            'usine_id'    => fn () => Usine::withoutGlobalScopes()->firstOrCreate(
                ['code' => 'TEST-DEFAULT'],
                ['nom' => 'Usine Test Default', 'type' => UsineType::USINE->value, 'statut' => 'active']
            )->id,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Produit $produit) {
            if ($produit->type !== ProduitType::SERVICE && !$produit->is_global && $produit->usine_id) {
                Stock::firstOrCreate(
                    ['produit_id' => $produit->id, 'usine_id' => $produit->usine_id],
                    ['qte_stock' => $this->stockQte, 'seuil_alerte_stock' => $this->stockSeuil]
                );
            }
        });
    }

    public function critique(): static
    {
        return $this->state(['is_critique' => true]);
    }

    public function withSeuil(int $seuil): static
    {
        return $this->afterCreating(function (Produit $produit) use ($seuil) {
            Stock::where('produit_id', $produit->id)
                ->where('usine_id', $produit->usine_id)
                ->update(['seuil_alerte_stock' => $seuil]);
        });
    }

    public function withStock(int $stock): static
    {
        return $this->afterCreating(function (Produit $produit) use ($stock) {
            Stock::where('produit_id', $produit->id)
                ->where('usine_id', $produit->usine_id)
                ->update(['qte_stock' => $stock]);
        });
    }
}
