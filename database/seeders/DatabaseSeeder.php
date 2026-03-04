<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            OrganisationSeeder::class,
            SiteSeeder::class,
            SuperAdminUserSeeder::class,
            AdminUserSeeder::class,
            ParametreSeeder::class,
            ProduitRouleauSeeder::class,
            ProduitPackSeeder::class,
            PrestataireMachinisteSeeder::class,
            StaffUserSeeder::class,
            VehiculeSeeder::class,
        ]);
    }
}
