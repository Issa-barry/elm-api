<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class StaffUserSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            [
                'nom'             => 'DIALLO',
                'prenom'          => 'Fatoumata',
                'phone'           => '+224620000010',
                'email'           => 'fatoumata.diallo@gmail.com',
                'quartier'        => 'Kaloum',
                'password'        => 'Staff@2025',
                'role'            => 'comptable',
            ],
            [
                'nom'             => 'DIALLO',
                'prenom'          => 'Thierno Oumar',
                'phone'           => '+224620000011',
                'email'           => 'tod@gmail.com',
                'quartier'        => 'Kaloum',
                'password'        => 'Staff@2025',
                'role'            => 'commerciale',
            ],
        ];

        foreach ($staff as $data) {
            $user = User::firstOrCreate(
                ['phone' => $data['phone']],
                [
                    'nom'              => $data['nom'],
                    'prenom'           => $data['prenom'],
                    'email'            => $data['email'],
                    'pays'             => 'Guinee',
                    'code_pays'        => 'GN',
                    'code_phone_pays'  => '+224',
                    'ville'            => 'Conakry',
                    'quartier'         => $data['quartier'],
                    'reference'        => $this->generateUserReference(),
                    'password'         => $data['password'],
                    'email_verified_at' => now(),
                ]
            );

            if (!$user->hasRole($data['role'])) {
                $user->assignRole($data['role']);
            }
        }
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
