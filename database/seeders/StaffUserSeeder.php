<?php

namespace Database\Seeders;

use App\Models\Usine;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffUserSeeder extends Seeder
{
    /**
     * Staff par usine (nom usine => liste).
     * Chaque user est créé une seule fois (phone unique global),
     * puis rattaché à son usine avec le rôle pivot approprié.
     */
    private array $staffParUsine = [
        'Usine de kaka' => [
            [
                'nom'        => 'DIALLO',
                'prenom'     => 'Fatoumata',
                'phone'      => '+224620000010',
                'email'      => 'fatoumata.diallo@gmail.com',
                'quartier'   => 'Kaloum',
                'password'   => 'Staff@2025',
                'role'       => 'comptable',
                'usine_role' => 'staff',
            ],
            [
                'nom'        => 'DIALLO',
                'prenom'     => 'Thierno Oumar',
                'phone'      => '+224620000011',
                'email'      => 'tod@gmail.com',
                'quartier'   => 'Kaloum',
                'password'   => 'Staff@2025',
                'role'       => 'commerciale',
                'usine_role' => 'staff',
            ],
            [
                'nom'        => 'BARRY',
                'prenom'     => 'Mamadou Saliou',
                'phone'      => '+224620000012',
                'email'      => null,
                'quartier'   => 'Matoto',
                'password'   => 'Staff@2025',
                'role'       => 'employe',
                'usine_role' => 'staff',
            ],
        ],
        'Usine de Matoto' => [
            [
                'nom'        => 'BALDE',
                'prenom'     => 'Ousmane',
                'phone'      => '+224620000020',
                'email'      => 'ousmane.balde@elm.gn',
                'quartier'   => 'Kaloum',
                'password'   => 'Staff@2025',
                'role'       => 'manager',
                'usine_role' => 'manager',
            ],
            [
                'nom'        => 'CAMARA',
                'prenom'     => 'Kadiatou',
                'phone'      => '+224620000021',
                'email'      => null,
                'quartier'   => 'Matam',
                'password'   => 'Staff@2025',
                'role'       => 'comptable',
                'usine_role' => 'staff',
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->staffParUsine as $usineNom => $staffList) {
            $usine = Usine::where('nom', $usineNom)->first();

            if (!$usine) {
                $this->command->warn("StaffUserSeeder : usine [{$usineNom}] introuvable, ignorée.");
                continue;
            }

            foreach ($staffList as $data) {
                $user = User::firstOrCreate(
                    ['phone' => $data['phone']],
                    [
                        'nom'               => $data['nom'],
                        'prenom'            => $data['prenom'],
                        'email'             => $data['email'],
                        'pays'              => 'Guinee',
                        'code_pays'         => 'GN',
                        'code_phone_pays'   => '+224',
                        'ville'             => 'Conakry',
                        'quartier'          => $data['quartier'],
                        'reference'         => User::generateUniqueReference(),
                        'password'          => $data['password'],
                        'email_verified_at' => now(),
                    ]
                );

                if (!$user->hasRole($data['role'])) {
                    $user->assignRole($data['role']);
                }

                if (!$user->usines()->where('usines.id', $usine->id)->exists()) {
                    $isDefault = $user->default_usine_id === null;
                    $user->usines()->attach($usine->id, [
                        'role'       => $data['usine_role'],
                        'is_default' => $isDefault,
                    ]);
                    if ($isDefault) {
                        $user->update(['default_usine_id' => $usine->id]);
                    }
                }
            }

            $this->command->info("StaffUserSeeder : " . count($staffList) . " utilisateur(s) traité(s) pour [{$usineNom}].");
        }
    }
}
