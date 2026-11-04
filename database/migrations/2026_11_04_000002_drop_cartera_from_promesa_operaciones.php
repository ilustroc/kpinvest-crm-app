<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: Drop obsolete cartera column and index from promesa_operaciones.
     */
    public function up(): void
    {
        if (!Schema::hasTable('promesa_operaciones')) {
            return;
        }

        // 1. Drop index if exists
        if ($this->indexExists('promesa_operaciones', 'promesa_operaciones_cartera_index')) {
            DB::statement('ALTER TABLE `promesa_operaciones` DROP INDEX `promesa_operaciones_cartera_index`');
        }

        // 2. Drop column if exists
        if ($this->hasColumn('promesa_operaciones', 'cartera')) {
            DB::statement('ALTER TABLE `promesa_operaciones` DROP COLUMN `cartera`');
        }
    }

    /**
     * Reverse the migrations: Restore cartera column and index.
     * Note: Restoring previous NULL/data values requires importing from pre-migration backup.
     */
    public function down(): void
    {
        if (!Schema::hasTable('promesa_operaciones')) {
            return;
        }

        // 1. Recreate column if missing
        if (!$this->hasColumn('promesa_operaciones', 'cartera')) {
            DB::statement('ALTER TABLE `promesa_operaciones` ADD COLUMN `cartera` VARCHAR(50) DEFAULT NULL AFTER `operacion`');
        }

        // 2. Recreate index if missing
        if (!$this->indexExists('promesa_operaciones', 'promesa_operaciones_cartera_index')) {
            DB::statement('ALTER TABLE `promesa_operaciones` ADD INDEX `promesa_operaciones_cartera_index` (`cartera`)');
        }
    }

    protected function hasColumn(string $table, string $column): bool
    {
        $database = DB::connection()->getDatabaseName();
        return DB::table('information_schema.columns')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $database = DB::connection()->getDatabaseName();
        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
