<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Site;
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

        $org   = Organisation::where('code', 'ELM-GN')->first();

        $moussa = $resolveUser('+22466617700', 'sidibemsa81@gmail.com');
        $moussa->fill([
            'phone'           => '+224656555520',
            'nom'             => 'SIDIBE',
            'prenom'          => 'Moussa',
            'email'           => 'sidibemsa81@gmail.com',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => 'Conakry',
            'quartier'        => 'Kaloum',
            'password'        => 'Staff@2025',
            'email_verified_at' => now(),
            'organisation_id' => $org?->id,
        ]);
        if (empty($moussa->reference)) {
            $moussa->reference = User::generateUniqueReference();
        }
        $moussa->save();

        $moussa->syncRoles(['admin_entreprise']);

        $siege = Site::where('nom', 'Usine de Matoto')->first();
        if ($siege) {
            // Moussa = propriétaire siège, accès à tous les sites.
            $moussaAffectations = [];
            foreach (Site::query()->select(['id', 'type'])->get() as $site) {
                $moussaAffectations[$site->id] = [
                    'role' => $site->id === $siege->id ? 'owner_siege' : 'manager',
                    'is_default' => $site->id === $siege->id,
                ];
            }

            $moussa->sites()->sync($moussaAffectations);
            $moussa->update(['default_site_id' => $siege->id]);
        }

        // Thierno Oumar est seedé dans StaffUserSeeder (manager de Matoto).
    }
}
