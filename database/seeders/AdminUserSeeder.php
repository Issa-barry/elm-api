<?php

namespace Database\Seeders;

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
                'pays' => 'Guinée',
                'code_pays' => 'GN',
                'code_phone_pays' => '+224',
                'ville' => 'Conakry',
                'quartier' => 'Kaloum',
                'password' => 'Jeux@2019',
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');
    }
}
