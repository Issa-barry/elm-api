<?php

namespace Database\Factories;

use App\Enums\OrganisationStatut;
use App\Models\Forfait;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    public function definition(): array
    {
        return [
            'nom'        => fake()->company(),
            'code'       => fake()->unique()->regexify('[A-Z]{3}-[A-Z0-9]{3}'),
            'email'      => fake()->companyEmail(),
            'phone'      => fake()->phoneNumber(),
            'pays'       => 'Guinee',
            'ville'      => 'Conakry',
            'statut'     => OrganisationStatut::ACTIVE,
            'forfait_id' => Forfait::firstOrCreate(
                ['slug' => 'starter'],
                ['nom' => 'Starter', 'prix' => 0, 'description' => 'Forfait de démarrage']
            )->id,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['statut' => OrganisationStatut::INACTIVE]);
    }

    public function suspended(): static
    {
        return $this->state(['statut' => OrganisationStatut::SUSPENDED]);
    }
}
