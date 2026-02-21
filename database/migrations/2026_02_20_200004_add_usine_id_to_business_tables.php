<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration d'ajout de usine_id sur toutes les tables métier + backfill des données existantes.
 *
 * Stratégie de backfill :
 *   1. Créer l'usine SIEGE (ELM-SIEGE) et l'usine opérationnelle (ELM-USN-01).
 *   2. Rattacher toutes les données existantes à ELM-USN-01.
 *   3. Affecter tous les utilisateurs existants à ELM-USN-01 (role = manager, is_default = true).
 *   4. Renseigner users.default_usine_id = ELM-USN-01.
 *
 * Note : le premier utilisateur (id le plus petit) est aussi affecté à ELM-SIEGE
 * comme OWNER_SIEGE afin qu'un compte siège existe dès le départ.
 */
return new class extends Migration
{
    /**
     * Tables métier qui reçoivent usine_id.
     * Format : [table, after_column]
     */
    private array $businessTables = [
        ['produits',        'id'],
        ['prestataires',    'id'],
        ['clients',         'id'],
        ['packings',        'id'],
        ['facture_packings','id'],
        ['versements',      'id'],
        ['parametres',      'id'],
    ];

    public function up(): void
    {
        // ── 1. Créer les deux usines fondatrices ──────────────────────────
        $now = now();

        $siegeId = DB::table('usines')->insertGetId([
            'nom'         => 'ELM Siège',
            'code'        => 'ELM-SIEGE',
            'type'        => 'siege',
            'statut'      => 'active',
            'localisation'=> null,
            'description' => 'Usine siège — vue consolidée',
            'parent_id'   => null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $usineId = DB::table('usines')->insertGetId([
            'nom'         => 'Usine Principale',
            'code'        => 'ELM-USN-01',
            'type'        => 'usine',
            'statut'      => 'active',
            'localisation'=> null,
            'description' => 'Usine opérationnelle principale',
            'parent_id'   => $siegeId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // ── 2. Ajouter usine_id sur les tables métier ─────────────────────
        foreach ($this->businessTables as [$table, $after]) {
            if (Schema::hasColumn($table, 'usine_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($after, $usineId) {
                $t->unsignedBigInteger('usine_id')
                    ->nullable()
                    ->after($after)
                    ->comment('Usine propriétaire de cet enregistrement');

                $t->index('usine_id');
            });

            // Backfill : rattacher à l'usine opérationnelle principale
            DB::table($table)->update(['usine_id' => $usineId]);

            // Rendre la colonne NOT NULL après le backfill (sauf parametres qui peuvent être globaux)
            if ($table !== 'parametres') {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('usine_id')->nullable(false)->change();
                });
            }

            // Ajouter la contrainte FK
            Schema::table($table, function (Blueprint $t) {
                $t->foreign('usine_id')
                    ->references('id')
                    ->on('usines')
                    ->restrictOnDelete();
            });
        }

        // ── 3. Backfill users ─────────────────────────────────────────────
        $userIds = DB::table('users')->orderBy('id')->pluck('id');

        if ($userIds->isNotEmpty()) {
            // Mettre à jour default_usine_id pour tous les utilisateurs
            DB::table('users')->update(['default_usine_id' => $usineId]);

            // Affecter tous les utilisateurs à l'usine principale
            $pivotRows = $userIds->map(fn ($uid) => [
                'user_id'    => $uid,
                'usine_id'   => $usineId,
                'role'       => 'manager',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            DB::table('user_usines')->insert($pivotRows);

            // Premier utilisateur → aussi OWNER_SIEGE sur le siège
            $firstUserId = $userIds->first();
            DB::table('user_usines')->insert([
                'user_id'    => $firstUserId,
                'usine_id'   => $siegeId,
                'role'       => 'owner_siege',
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Supprimer les FK et colonnes usine_id
        foreach ($this->businessTables as [$table]) {
            if (!Schema::hasColumn($table, 'usine_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                // Supprimer la FK si elle existe
                try {
                    $t->dropForeign(["{$table}_usine_id_foreign"]);
                } catch (\Throwable) {}

                $t->dropIndex(["{$table}_usine_id_index"]);
                $t->dropColumn('usine_id');
            });
        }

        // Supprimer les pivots et remettre users.default_usine_id à null
        DB::table('user_usines')->truncate();
        DB::table('users')->update(['default_usine_id' => null]);

        // Supprimer les usines fondatrices créées par ce backfill
        DB::table('usines')->whereIn('code', ['ELM-SIEGE', 'ELM-USN-01'])->delete();
    }
};
