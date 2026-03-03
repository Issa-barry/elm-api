<?php

namespace Database\Seeders;

use App\Models\Usine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SuperAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdminRole->syncPermissions(Permission::all());

        $superAdmins = [
            [
                'phone' => '+224666101001',
                'nom' => 'BARRY',
                'prenom' => 'Issa',
                'email' => 'issabarry67@gmail.com',
                'pays' => 'Guinee',
                'code_pays' => 'GN',
                'code_phone_pays' => '+224',
                'ville' => 'Conakry',
                'quartier' => 'Kaloum',
                'password' => 'Jeux@2019',
            ],
            [
                'phone' => '+224666101001',
                'nom' => 'DIALLO',
                'prenom' => 'Abdoulaye',
                'email' => 'abdoulaye.gn@gmail.com',
                'pays' => 'Guinee',
                'code_pays' => 'GN',
                'code_phone_pays' => '+224',
                'ville' => 'Conakry',
                'quartier' => 'Non renseigne',
                'password' => 'Omega2026!',
            ],
        ];

        $siege = Usine::query()->where('nom', 'Usine de Matoto')->first();

        foreach ($superAdmins as $data) {
            $user = User::withTrashed()->where('phone', $data['phone'])->first();

            if (!$user && !empty($data['email'])) {
                $user = User::withTrashed()->where('email', $data['email'])->first();
            }

            if (!$user) {
                $user = new User();
            } elseif ($user->trashed()) {
                $user->restore();
            }

            if (!$user->exists) {
                $user->fill([
                    'phone' => $data['phone'],
                    'nom' => $data['nom'],
                    'prenom' => $data['prenom'],
                    'email' => $data['email'],
                    'pays' => $data['pays'],
                    'code_pays' => $data['code_pays'],
                    'code_phone_pays' => $data['code_phone_pays'],
                    'ville' => $data['ville'],
                    'quartier' => $data['quartier'] ?? 'Non renseigne',
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                    'reference' => User::generateUniqueReference(),
                ]);
                $user->save();
            } else {
                $updates = [];
                if (!$user->email && $data['email']) {
                    $updates['email'] = $data['email'];
                }
                if (!$user->email_verified_at) {
                    $updates['email_verified_at'] = now();
                }
                if (!$user->reference) {
                    $updates['reference'] = User::generateUniqueReference();
                }
                if (!empty($updates)) {
                    $user->fill($updates);
                    $user->save();
                }
            }

            if (!$user->hasRole('super_admin')) {
                $user->assignRole('super_admin');
            }

            if ($siege) {
                if (!$user->usines()->where('usines.id', $siege->id)->exists()) {
                    $user->usines()->attach($siege->id, [
                        'role' => 'owner_siege',
                        'is_default' => $user->default_usine_id === null,
                    ]);
                } else {
                    $user->usines()->updateExistingPivot($siege->id, ['role' => 'owner_siege']);
                }

                if ($user->default_usine_id === null) {
                    $user->update(['default_usine_id' => $siege->id]);
                }
            }
        }
    }
}
