<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: Widen ccd_clientes.numdoc from VARCHAR(12) to VARCHAR(30)
     * to support DNI (8 digits), RUC (11 digits), and CE/PTP (up to 15 alphanumeric).
     */
    public function up(): void
    {
        if (!Schema::hasTable('ccd_clientes')) {
            return;
        }

        if ($this->hasColumn('ccd_clientes', 'numdoc')) {
            DB::statement('ALTER TABLE `ccd_clientes` MODIFY COLUMN `numdoc` VARCHAR(30) NOT NULL');
        }
    }

    /**
     * Reverse the migrations: Revert ccd_clientes.numdoc back to VARCHAR(12).
     */
    public function down(): void
    {
        if (!Schema::hasTable('ccd_clientes')) {
            return;
        }

        if ($this->hasColumn('ccd_clientes', 'numdoc')) {
            DB::statement('ALTER TABLE `ccd_clientes` MODIFY COLUMN `numdoc` VARCHAR(12) NOT NULL');
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
};
