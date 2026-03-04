<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffUserSeeder extends Seeder
{
    /**
     * Staff par site (nom site => liste).
     * Chaque user est créé une seule fois (phone unique global),
     * puis rattaché à son usine avec le rôle pivot approprié.
     */
    private array $staffParSite = [
        'Usine de kaka' => [
            [
                'nom'        => 'BAH',
                'prenom'     => 'Mamadou Alpha',
                'phone'      => '+224620000013',
                'email'      => 'mamadou.alpha.bah@elm.gn',
                'quartier'   => 'Kaka',
                'password'   => 'Staff@2025',
                'role'       => 'manager',
                'usine_role' => 'manager',
            ],
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
                'nom'        => 'CONDE',
                'prenom'     => 'Mariama',
                'phone'      => '+224620000014',
                'email'      => 'mariama.conde@elm.gn',
                'quartier'   => 'Kaka',
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
                'nom'        => 'DIALLO',
                'prenom'     => 'Thierno Oumar',
                'phone'      => '+224620000020',
                'email'      => 'tod@elm.gn',
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
            [
                'nom'        => 'SYLLA',
                'prenom'     => 'Aissatou',
                'phone'      => '+224620000022',
                'email'      => 'aissatou.sylla@elm.gn',
                'quartier'   => 'Matoto',
                'password'   => 'Staff@2025',
                'role'       => 'commerciale',
                'usine_role' => 'staff',
            ],
            [
                'nom'        => 'KEITA',
                'prenom'     => 'Ibrahima',
                'phone'      => '+224620000023',
                'email'      => null,
                'quartier'   => 'Matoto',
                'password'   => 'Staff@2025',
                'role'       => 'employe',
                'usine_role' => 'staff',
            ],
        ],
    ];

    public function run(): void
    {
        $org = Organisation::where('code', 'ELM-GN')->first();

        foreach ($this->staffParSite as $siteNom => $staffList) {
            $usine = Site::where('nom', $siteNom)->first();

            if (!$usine) {
                $this->command->warn("StaffUserSeeder : site [{$siteNom}] introuvable, ignoré.");
                continue;
            }

            foreach ($staffList as $data) {
                $user = User::withTrashed()->where('phone', $data['phone'])->first();

                if (!$user) {
                    $user = new User();
                } elseif ($user->trashed()) {
                    $user->restore();
                }

                $user->fill([
                    'phone'             => $data['phone'],
                    'nom'               => $data['nom'],
                    'prenom'            => $data['prenom'],
                    'email'             => $data['email'],
                    'pays'              => 'Guinee',
                    'code_pays'         => 'GN',
                    'code_phone_pays'   => '+224',
                    'ville'             => 'Conakry',
                    'quartier'          => $data['quartier'],
                    'password'          => $data['password'],
                    'email_verified_at' => now(),
                    'organisation_id'   => $org?->id,
                ]);

                if (empty($user->reference)) {
                    $user->reference = User::generateUniqueReference();
                }
                $user->save();

                $user->syncRoles([$data['role']]);

                if (!$user->sites()->where('sites.id', $usine->id)->exists()) {
                    $isDefault = $user->default_site_id === null;
                    $user->sites()->attach($usine->id, [
                        'role'       => $data['usine_role'],
                        'is_default' => $isDefault,
                    ]);
                    if ($isDefault) {
                        $user->update(['default_site_id' => $usine->id]);
                    }
                }
            }

            $this->command->info("StaffUserSeeder : " . count($staffList) . " utilisateur(s) traité(s) pour [{$siteNom}].");
        }
    }
}
