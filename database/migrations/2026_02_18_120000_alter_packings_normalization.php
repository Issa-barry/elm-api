<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packings', function (Blueprint $table) {
            $table->index(['prestataire_id', 'date'], 'packings_prestataire_date_index');
            $table->index(['statut', 'facture_id'], 'packings_statut_facture_index');
        });

        if (!$this->supportsCheckConstraints()) {
            return;
        }
        $this->addCheckConstraintIfMissing('chk_packings_nb_rouleaux_non_neg', 'nb_rouleaux >= 0');
        $this->addCheckConstraintIfMissing('chk_packings_prix_par_rouleau_non_neg', 'prix_par_rouleau >= 0');
        $this->addCheckConstraintIfMissing('chk_packings_montant_coherent', 'montant = (nb_rouleaux * prix_par_rouleau)');
    }

    public function down(): void
    {
        if ($this->supportsCheckConstraints()) {
            $this->dropCheckConstraintIfExists('chk_packings_montant_coherent');
            $this->dropCheckConstraintIfExists('chk_packings_prix_par_rouleau_non_neg');
            $this->dropCheckConstraintIfExists('chk_packings_nb_rouleaux_non_neg');
        }

        Schema::table('packings', function (Blueprint $table) {
            $table->dropIndex('packings_statut_facture_index');
            $table->dropIndex('packings_prestataire_date_index');
        });
    }

    private function supportsCheckConstraints(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $version = $this->databaseVersion();

        if ($this->isMariaDb($version)) {
            return true;
        }

        return version_compare($this->extractSemver($version), '8.0.16', '>=');
    }

    private function addCheckConstraintIfMissing(string $constraintName, string $expression): void
    {
        if ($this->checkConstraintExists($constraintName)) {
            return;
        }

        DB::statement(
            sprintf(
                'ALTER TABLE `packings` ADD CONSTRAINT `%s` CHECK (%s)',
                $constraintName,
                $expression
            )
        );
    }

    private function dropCheckConstraintIfExists(string $constraintName): void
    {
        if (!$this->checkConstraintExists($constraintName)) {
            return;
        }

        if ($this->isMariaDb($this->databaseVersion())) {
            DB::statement(sprintf('ALTER TABLE `packings` DROP CONSTRAINT `%s`', $constraintName));
            return;
        }

        DB::statement(sprintf('ALTER TABLE `packings` DROP CHECK `%s`', $constraintName));
    }

    private function checkConstraintExists(string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'packings')
            ->where('constraint_type', 'CHECK')
            ->where('constraint_name', $constraintName)
            ->exists();
    }

    private function databaseVersion(): string
    {
        $row = DB::selectOne('SELECT VERSION() as version');

        return (string) ($row->version ?? '');
    }

    private function isMariaDb(string $version): bool
    {
        return str_contains(strtolower($version), 'mariadb');
    }

    private function extractSemver(string $version): string
    {
        if (preg_match('/\d+\.\d+\.\d+/', $version, $matches)) {
            return $matches[0];
        }

        return '0.0.0';
    }
};
