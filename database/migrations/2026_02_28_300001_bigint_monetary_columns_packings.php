<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passe les colonnes monétaires de INT à BIGINT.
 *
 * Problème : montant = nb_rouleaux × prix_par_rouleau
 *   INT max = 2 147 483 647
 *   Exemple : 99 911 100 × 500 = 49 955 550 000 → overflow silencieux → troncature
 *
 * Colonnes concernées :
 *   - packings : nb_rouleaux, prix_par_rouleau, montant
 *   - facture_packings : montant_total
 *
 * Le check constraint chk_packings_montant_coherent est supprimé et recréé
 * pour que l'expression s'évalue en BIGINT (pas d'overflow en MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── packings ──────────────────────────────────────────────────────
        // Supprimer le check constraint avant d'altérer les colonnes
        $this->dropCheckConstraintIfExists('packings', 'chk_packings_montant_coherent');
        $this->dropCheckConstraintIfExists('packings', 'chk_packings_nb_rouleaux_non_neg');
        $this->dropCheckConstraintIfExists('packings', 'chk_packings_prix_par_rouleau_non_neg');

        Schema::table('packings', function (Blueprint $table) {
            $table->bigInteger('nb_rouleaux')->default(0)->change();
            $table->bigInteger('prix_par_rouleau')->default(0)->change();
            $table->bigInteger('montant')->default(0)->change();
        });

        // Recréer les check constraints (BIGINT × BIGINT = BIGINT, pas d'overflow)
        if ($this->supportsCheckConstraints()) {
            $this->addCheckConstraint(
                'packings',
                'chk_packings_nb_rouleaux_non_neg',
                'nb_rouleaux >= 0'
            );
            $this->addCheckConstraint(
                'packings',
                'chk_packings_prix_par_rouleau_non_neg',
                'prix_par_rouleau >= 0'
            );
            $this->addCheckConstraint(
                'packings',
                'chk_packings_montant_coherent',
                'montant = (nb_rouleaux * prix_par_rouleau)'
            );
        }

        // ── facture_packings ──────────────────────────────────────────────
        Schema::table('facture_packings', function (Blueprint $table) {
            $table->bigInteger('montant_total')->default(0)->change();
        });
    }

    public function down(): void
    {
        $this->dropCheckConstraintIfExists('packings', 'chk_packings_montant_coherent');
        $this->dropCheckConstraintIfExists('packings', 'chk_packings_nb_rouleaux_non_neg');
        $this->dropCheckConstraintIfExists('packings', 'chk_packings_prix_par_rouleau_non_neg');

        Schema::table('packings', function (Blueprint $table) {
            $table->integer('nb_rouleaux')->default(0)->change();
            $table->integer('prix_par_rouleau')->default(0)->change();
            $table->integer('montant')->default(0)->change();
        });

        if ($this->supportsCheckConstraints()) {
            $this->addCheckConstraint('packings', 'chk_packings_nb_rouleaux_non_neg', 'nb_rouleaux >= 0');
            $this->addCheckConstraint('packings', 'chk_packings_prix_par_rouleau_non_neg', 'prix_par_rouleau >= 0');
            $this->addCheckConstraint('packings', 'chk_packings_montant_coherent', 'montant = (nb_rouleaux * prix_par_rouleau)');
        }

        Schema::table('facture_packings', function (Blueprint $table) {
            $table->integer('montant_total')->default(0)->change();
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function supportsCheckConstraints(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $version = $this->databaseVersion();

        if (str_contains(strtolower($version), 'mariadb')) {
            return true;
        }

        if (preg_match('/\d+\.\d+\.\d+/', $version, $m)) {
            return version_compare($m[0], '8.0.16', '>=');
        }

        return false;
    }

    private function addCheckConstraint(string $table, string $name, string $expression): void
    {
        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})"
        );
    }

    private function dropCheckConstraintIfExists(string $table, string $name): void
    {
        $exists = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_type', 'CHECK')
            ->where('constraint_name', $name)
            ->exists();

        if (!$exists) {
            return;
        }

        $version = $this->databaseVersion();

        if (str_contains(strtolower($version), 'mariadb')) {
            DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$name}`");
        } else {
            DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
        }
    }

    private function databaseVersion(): string
    {
        $row = DB::selectOne('SELECT VERSION() as version');
        return (string) ($row->version ?? '');
    }
};
