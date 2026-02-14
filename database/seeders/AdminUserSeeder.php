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
                'pays' => 'Guinee',
                'code_pays' => 'GN',
                'code_phone_pays' => '+224',
                'ville' => 'Conakry',
                'quartier' => 'Kaloum',
                'reference' => $this->generateUserReference(),
                'password' => 'Jeux@2019',
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');
    }

    private function generateUserReference(): string
    {
        $prefix = 'USR-' . now()->format('Ymd') . '-';
        $sequence = (User::withTrashed()->max('id') ?? 0) + 1;

        do {
            $reference = $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $exists = User::withTrashed()->where('reference', $reference)->exists();
            $sequence++;
        } while ($exists);

        return $reference;
    }
}