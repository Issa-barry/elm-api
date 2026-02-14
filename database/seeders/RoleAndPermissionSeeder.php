<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Supprimer les anciennes permissions
        Permission::query()->delete();

        // Permissions CRUD par module (style Strapi)
        $modules = [
            'users'            => ['create', 'read', 'update', 'delete'],
            'produits'         => ['create', 'read', 'update', 'delete'],
            'prestataires'     => ['create', 'read', 'update', 'delete'],
            'clients'          => ['create', 'read', 'update', 'delete'],
            'packings'         => ['create', 'read', 'update', 'delete'],
            'facture-packings' => ['create', 'read', 'update', 'delete'],
            'versements'       => ['create', 'read', 'delete'],
            'parametres'       => ['read', 'update'],
        ];

        // Creer toutes les permissions
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}"]);
            }
        }

        // ADMIN : toutes les permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // MANAGER : CRUD opérationnel + lecture users/parametres
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $managerPermissions = [];

        $operationalModules = ['produits', 'prestataires', 'clients', 'packings', 'facture-packings', 'versements'];
        foreach ($operationalModules as $module) {
            foreach ($modules[$module] as $action) {
                $managerPermissions[] = "{$module}.{$action}";
            }
        }

        $managerPermissions[] = 'users.read';
        $managerPermissions[] = 'parametres.read';

        $manager->syncPermissions($managerPermissions);

        // EMPLOYE : lecture sur tous les modules + create/update packings
        $employe = Role::firstOrCreate(['name' => 'employe']);
        $employePermissions = [
            'users.read',
            'produits.read',
            'prestataires.read',
            'clients.read',
            'packings.read', 'packings.create', 'packings.update',
            'facture-packings.read',
            'versements.read',
            'parametres.read',
        ];
        $employe->syncPermissions($employePermissions);
    }
}
