<?php

namespace Database\Seeders;

use App\Models\Usine;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['phone' => '+224666177006'],
            [
                'nom' => 'BARRY',
                'prenom' => 'Issa',
                'email' => 'issabarry67@gmail.com',
                'pays' => 'Guinee',
                'code_pays' => 'GN',
                'code_phone_pays' => '+224',
                'ville' => 'Conakry',
                'quartier' => 'Kaloum',
                'reference' => User::generateUniqueReference(),
                'password' => 'Jeux@2019',
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');

        // Rattacher l'admin aux deux usines
        $siege = Usine::where('nom', 'Usine de Matoto')->first();
        $usine = Usine::where('nom', 'Usine de kaka')->first();

        if ($siege && ! $admin->usines()->where('usines.id', $siege->id)->exists()) {
            $admin->usines()->attach($siege->id, ['role' => 'owner_siege', 'is_default' => false]);
        }

        if ($usine && ! $admin->usines()->where('usines.id', $usine->id)->exists()) {
            $admin->usines()->attach($usine->id, ['role' => 'manager', 'is_default' => true]);
            $admin->update(['default_usine_id' => $usine->id]);
        }
    }
}