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
            'users'                 => ['create', 'read', 'update', 'delete'],
            'produits'              => ['create', 'read', 'update', 'delete'],
            'prestataires'          => ['create', 'read', 'update', 'delete'],
            'clients'               => ['create', 'read', 'update', 'delete'],
            'packings'              => ['create', 'read', 'update', 'delete'],
            'facture-packings'      => ['create', 'read', 'update', 'delete'],
            'versements'            => ['create', 'read', 'delete'],
            'parametres'            => ['read', 'update'],
            // Module livraison
            'proprietaires'         => ['create', 'read', 'update', 'delete'],
            'livreurs'              => ['create', 'read', 'update', 'delete'],
            'vehicules'             => ['create', 'read', 'update', 'delete'],
            'sorties'               => ['create', 'read', 'update'],
            'factures-livraisons'   => ['create', 'read'],
            'encaissements'         => ['create', 'read'],
            'commissions'           => ['create', 'read'],
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

        $operationalModules = [
            'produits', 'prestataires', 'clients', 'packings', 'facture-packings', 'versements',
            'proprietaires', 'livreurs', 'vehicules', 'sorties', 'factures-livraisons', 'encaissements', 'commissions',
        ];
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

        // COMPTABLE : gestion financière (factures, versements) + lecture opérationnelle
        $comptable = Role::firstOrCreate(['name' => 'comptable']);
        $comptable->syncPermissions([
            'produits.read',
            'prestataires.read',
            'clients.read',
            'packings.read',
            'facture-packings.create', 'facture-packings.read', 'facture-packings.update', 'facture-packings.delete',
            'versements.create', 'versements.read', 'versements.delete',
            // Livraison financier
            'proprietaires.read',
            'livreurs.read',
            'vehicules.read',
            'sorties.read',
            'factures-livraisons.create', 'factures-livraisons.read',
            'encaissements.create', 'encaissements.read',
            'commissions.create', 'commissions.read',
        ]);

        // COMMERCIALE : gestion commerciale (clients, packings, factures en lecture)
        $commerciale = Role::firstOrCreate(['name' => 'commerciale']);
        $commerciale->syncPermissions([
            'produits.read',
            'prestataires.read',
            'clients.create', 'clients.read', 'clients.update', 'clients.delete',
            'packings.create', 'packings.read', 'packings.update', 'packings.delete',
            'facture-packings.read',
            'versements.read',
        ]);
    }
}
