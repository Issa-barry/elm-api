<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étape 3/4 — Backfill : créer l'organisation par défaut et rattacher
 * toutes les usines et tous les utilisateurs existants.
 *
 * Idempotent : si une organisation ELM-GN existe déjà, on réutilise son ID.
 * Ne touche qu'aux lignes dont organisation_id est NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Si aucune donnée existante à migrer, on saute la migration.
        // (DB fraîche → aucun usine/user ne précède la table organisations.
        //  Le OrganisationSeeder s'en charge pour les déploiements from scratch.)
        $hasOrphanData = DB::table('usines')->whereNull('organisation_id')->exists()
            || DB::table('users')->whereNull('organisation_id')->exists();

        if (!$hasOrphanData) {
            return;
        }

        // Récupérer ou créer l'organisation par défaut
        $org = DB::table('organisations')->where('code', 'ELM-GN')->first();

        if (!$org) {
            $orgId = DB::table('organisations')->insertGetId([
                'nom'        => 'ELM Guinée',
                'code'       => 'ELM-GN',
                'email'      => 'contact@elm.gn',
                'pays'       => 'Guinee',
                'ville'      => 'Conakry',
                'statut'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $orgId = $org->id;
        }

        // Rattacher toutes les usines sans organisation
        DB::table('usines')
            ->whereNull('organisation_id')
            ->update(['organisation_id' => $orgId, 'updated_at' => $now]);

        // Rattacher tous les utilisateurs sans organisation
        DB::table('users')
            ->whereNull('organisation_id')
            ->update(['organisation_id' => $orgId, 'updated_at' => $now]);
    }

    public function down(): void
    {
        // On ne peut pas déterminer avec certitude quels enregistrements
        // avaient organisation_id=NULL avant le backfill → rollback conservatif
        $org = DB::table('organisations')->where('code', 'ELM-GN')->first();
        if ($org) {
            DB::table('usines')
                ->where('organisation_id', $org->id)
                ->update(['organisation_id' => null]);
            DB::table('users')
                ->where('organisation_id', $org->id)
                ->update(['organisation_id' => null]);
            DB::table('organisations')->where('id', $org->id)->delete();
        }
    }
};
