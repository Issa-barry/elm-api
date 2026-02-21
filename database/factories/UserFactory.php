<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nom'              => mb_strtoupper(fake()->lastName(), 'UTF-8'),
            'prenom'           => fake()->firstName(),
            'email'            => fake()->unique()->safeEmail(),
            'email_verified_at'=> now(),
            'password'         => static::$password ??= Hash::make('password'),
            'remember_token'   => Str::random(10),
            'type'             => 'staff',
            'phone'            => '+224620' . fake()->numerify('######'),
            'pays'             => 'Guinee',
            'code_pays'        => 'GN',
            'code_phone_pays'  => '+224',
            'ville'            => 'Conakry',
            'quartier'         => fake()->word(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
