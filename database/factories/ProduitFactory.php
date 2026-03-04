<?php

namespace Database\Factories;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\SiteType;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Site;
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
            'site_id'    => fn () => Site::withoutGlobalScopes()->firstOrCreate(
                ['code' => 'TEST-DEFAULT'],
                ['nom' => 'Site Test Default', 'type' => SiteType::USINE->value, 'statut' => 'active']
            )->id,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Produit $produit) {
            if ($produit->type !== ProduitType::SERVICE && !$produit->is_global && $produit->site_id) {
                Stock::firstOrCreate(
                    ['produit_id' => $produit->id, 'site_id' => $produit->site_id],
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
                ->where('site_id', $produit->site_id)
                ->update(['seuil_alerte_stock' => $seuil]);
        });
    }

    public function withStock(int $stock): static
    {
        return $this->afterCreating(function (Produit $produit) use ($stock) {
            Stock::where('produit_id', $produit->id)
                ->where('site_id', $produit->site_id)
                ->update(['qte_stock' => $stock]);
        });
    }
}
