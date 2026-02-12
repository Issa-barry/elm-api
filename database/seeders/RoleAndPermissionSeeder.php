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

        // Permissions par module
        $modules = [
            'users'            => ['index', 'show', 'store', 'update', 'destroy', 'toggle-status'],
            'produits'         => ['index', 'show', 'store', 'update', 'destroy', 'search', 'statistics', 'update-stock', 'change-status', 'archive', 'unarchive', 'archived-list'],
            'prestataires'     => ['index', 'show', 'store', 'update', 'destroy', 'toggle-status'],
            'clients'          => ['index', 'show', 'store', 'update', 'destroy', 'toggle-status'],
            'packings'         => ['index', 'show', 'store', 'update', 'destroy', 'change-statut', 'valider'],
            'facture-packings' => ['index', 'show', 'store', 'destroy', 'preview', 'comptabilite'],
            'versements'       => ['index', 'store', 'destroy'],
            'parametres'       => ['index', 'update'],
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

        // MANAGER : CRUD operationnel + lecture users/parametres
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $managerPermissions = [];

        $operationalModules = ['produits', 'prestataires', 'clients', 'packings', 'facture-packings', 'versements'];
        foreach ($operationalModules as $module) {
            foreach ($modules[$module] as $action) {
                $managerPermissions[] = "{$module}.{$action}";
            }
        }

        // Lecture users et parametres
        $managerPermissions[] = 'users.index';
        $managerPermissions[] = 'users.show';
        $managerPermissions[] = 'parametres.index';

        $manager->syncPermissions($managerPermissions);

        // EMPLOYE : lecture sur tous les modules + creation/modification packings
        $employe = Role::firstOrCreate(['name' => 'employe']);
        $employePermissions = [
            'users.index', 'users.show',
            'produits.index', 'produits.show', 'produits.search', 'produits.statistics', 'produits.archived-list',
            'prestataires.index', 'prestataires.show',
            'clients.index', 'clients.show',
            'packings.index', 'packings.show', 'packings.store', 'packings.update',
            'facture-packings.index', 'facture-packings.show', 'facture-packings.preview', 'facture-packings.comptabilite',
            'versements.index',
            'parametres.index',
        ];
        $employe->syncPermissions($employePermissions);
    }
}
