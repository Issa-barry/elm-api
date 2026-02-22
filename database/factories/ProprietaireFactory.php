<?php

namespace Database\Factories;

use App\Models\Proprietaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proprietaire>
 */
class ProprietaireFactory extends Factory
{
    protected $model = Proprietaire::class;

    public function definition(): array
    {
        return [
            'nom'      => mb_strtoupper(fake()->lastName(), 'UTF-8'),
            'prenom'   => fake()->firstName(),
            'phone'    => '+224620' . fake()->unique()->numerify('######'),
            'email'    => fake()->unique()->safeEmail(),
            'pays'     => 'Guinee',
            'ville'    => 'Conakry',
            'quartier' => fake()->word(),
            'is_active' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(['is_active' => false]);
    }
}
