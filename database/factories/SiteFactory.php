<?php

namespace Database\Factories;

use App\Enums\SiteStatut;
use App\Enums\SiteType;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'nom'     => fake()->company() . ' ' . fake()->city(),
            'code'    => fake()->unique()->regexify('[A-Z]{3}-[A-Z0-9]{4}'),
            'type'    => SiteType::USINE,
            'statut'  => SiteStatut::ACTIVE,
            'pays'    => 'Guinee',
            'ville'   => 'Conakry',
            'quartier' => fake()->city(),
        ];
    }

    public function siege(): static
    {
        return $this->state(['type' => SiteType::SIEGE]);
    }

    public function inactive(): static
    {
        return $this->state(['statut' => SiteStatut::INACTIVE]);
    }
}
