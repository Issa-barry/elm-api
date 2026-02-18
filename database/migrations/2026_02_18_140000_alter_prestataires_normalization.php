<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('prestataires', ['type'], 'prestataires_type_index');
        $this->addIndexIfMissing('prestataires', ['is_active'], 'prestataires_is_active_index');
        $this->addIndexIfMissing('prestataires', ['specialite'], 'prestataires_specialite_index');

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `prestataires` MODIFY `nom` VARCHAR(255) NULL');
            DB::statement('ALTER TABLE `prestataires` MODIFY `prenom` VARCHAR(255) NULL');
            DB::statement("ALTER TABLE `prestataires` MODIFY `tarif_horaire` INT NULL COMMENT 'Tarif horaire en GNF/heure'");

            if ($this->supportsCheckConstraints()) {
                $this->addCheckConstraintIfMissing(
                    'chk_prestataires_tarif_horaire_non_neg',
                    'tarif_horaire IS NULL OR tarif_horaire >= 0'
                );
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if ($this->supportsCheckConstraints()) {
                $this->dropCheckConstraintIfExists('chk_prestataires_tarif_horaire_non_neg');
            }

            DB::table('prestataires')->whereNull('nom')->update(['nom' => '']);
            DB::table('prestataires')->whereNull('prenom')->update(['prenom' => '']);

            DB::statement('ALTER TABLE `prestataires` MODIFY `nom` VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE `prestataires` MODIFY `prenom` VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE `prestataires` MODIFY `tarif_horaire` INT NULL');
        }

        $this->dropIndexIfExists('prestataires', 'prestataires_specialite_index');
        $this->dropIndexIfExists('prestataires', 'prestataires_is_active_index');
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->where('indexname', $indexName)
                ->exists();
        }

        return false;
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
                'ALTER TABLE `prestataires` ADD CONSTRAINT `%s` CHECK (%s)',
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
            DB::statement(sprintf('ALTER TABLE `prestataires` DROP CONSTRAINT `%s`', $constraintName));
            return;
        }

        DB::statement(sprintf('ALTER TABLE `prestataires` DROP CHECK `%s`', $constraintName));
    }

    private function checkConstraintExists(string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'prestataires')
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
