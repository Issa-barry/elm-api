<?php

namespace Database\Seeders;

use App\Models\Usine;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $resolveUser = static function (string $phone, ?string $email): User {
            $user = User::withTrashed()->where('phone', $phone)->first();

            if (!$user && $email) {
                $user = User::withTrashed()->where('email', $email)->first();
            }

            if (!$user) {
                $user = new User();
            } elseif ($user->trashed()) {
                $user->restore();
            }

            return $user;
        };

        $moussa = $resolveUser('+22466617700', 'issabarry67@gmail.com');
        $moussa->fill([
            'phone' => '+224666101011',
            'nom' => 'SIDIBE',
            'prenom' => 'Moussa',
            'email' => 'issabarry67@gmail.com',
            'pays' => 'Guinee',
            'code_pays' => 'GN',
            'code_phone_pays' => '+224',
            'ville' => 'Conakry',
            'quartier' => 'Kaloum',
            'password' => 'Staff@2025',
            'email_verified_at' => now(),
        ]);
        if (empty($moussa->reference)) {
            $moussa->reference = User::generateUniqueReference();
        }
        $moussa->save();

        $moussa->syncRoles(['admin_entreprise']);

        $siege = Usine::where('nom', 'Usine de Matoto')->first();
        if ($siege) {
            // Moussa = propriétaire siège, accès à toutes les usines.
            $moussaAffectations = [];
            foreach (Usine::query()->select(['id', 'type'])->get() as $usine) {
                $moussaAffectations[$usine->id] = [
                    'role' => $usine->id === $siege->id ? 'owner_siege' : 'manager',
                    'is_default' => $usine->id === $siege->id,
                ];
            }

            $moussa->usines()->sync($moussaAffectations);
            $moussa->update(['default_usine_id' => $siege->id]);
        }

        // Thierno Oumar est seedé dans StaffUserSeeder (manager de Matoto).
    }
}
