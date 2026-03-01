<?php

namespace Database\Factories;

use App\Models\Livreur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Livreur>
 */
class LivreurFactory extends Factory
{
    protected $model = Livreur::class;

    public function definition(): array
    {
        return [
            'nom'      => mb_strtoupper(fake()->lastName(), 'UTF-8'),
            'prenom'   => fake()->firstName(),
            'phone'    => '+224621' . fake()->unique()->numerify('######'),
            'is_active' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(['is_active' => false]);
    }
}
