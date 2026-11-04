<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: Drop obsolete tipo column from pagos_lotes.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pagos_lotes')) {
            return;
        }

        // 1. Drop any index referencing tipo if exists
        $indexes = $this->getIndexesForColumn('pagos_lotes', 'tipo');
        foreach ($indexes as $indexName) {
            DB::statement("ALTER TABLE `pagos_lotes` DROP INDEX `{$indexName}`");
        }

        // 2. Drop column if exists
        if ($this->hasColumn('pagos_lotes', 'tipo')) {
            DB::statement('ALTER TABLE `pagos_lotes` DROP COLUMN `tipo`');
        }
    }

    /**
     * Reverse the migrations: Restore tipo column with default 'propia'.
     * Note: Restoring specific previous values requires importing from pre-migration backup.
     */
    public function down(): void
    {
        if (!Schema::hasTable('pagos_lotes')) {
            return;
        }

        if (!$this->hasColumn('pagos_lotes', 'tipo')) {
            DB::statement("ALTER TABLE `pagos_lotes` ADD COLUMN `tipo` VARCHAR(50) NOT NULL DEFAULT 'propia' AFTER `id`");
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

    /**
     * @return array<string>
     */
    protected function getIndexesForColumn(string $table, string $column): array
    {
        $database = DB::connection()->getDatabaseName();
        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->pluck('index_name')
            ->unique()
            ->all();
    }
};
