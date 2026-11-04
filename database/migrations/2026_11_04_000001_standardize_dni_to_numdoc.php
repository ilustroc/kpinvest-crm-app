<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: Standardize dni to numdoc across all client document fields.
     */
    public function up(): void
    {
        // 1. cliente_bloqueos
        if (Schema::hasTable('cliente_bloqueos')) {
            if ($this->hasColumn('cliente_bloqueos', 'dni') && !$this->hasColumn('cliente_bloqueos', 'numdoc')) {
                DB::statement('ALTER TABLE `cliente_bloqueos` RENAME COLUMN `dni` TO `numdoc`');
                DB::statement('ALTER TABLE `cliente_bloqueos` MODIFY COLUMN `numdoc` VARCHAR(30) NOT NULL');
                $this->renameIndexIfExists('cliente_bloqueos', 'cliente_bloqueos_user_id_dni_unique', 'cliente_bloqueos_user_id_numdoc_unique');
                $this->renameIndexIfExists('cliente_bloqueos', 'cliente_bloqueos_dni_index', 'cliente_bloqueos_numdoc_index');
            }
        }

        // 2. cna_solicitudes
        if (Schema::hasTable('cna_solicitudes')) {
            if ($this->hasColumn('cna_solicitudes', 'dni') && !$this->hasColumn('cna_solicitudes', 'numdoc')) {
                DB::statement('ALTER TABLE `cna_solicitudes` RENAME COLUMN `dni` TO `numdoc`');
                DB::statement('ALTER TABLE `cna_solicitudes` MODIFY COLUMN `numdoc` VARCHAR(30) NOT NULL');
                $this->renameIndexIfExists('cna_solicitudes', 'cna_solicitudes_dni_index', 'cna_solicitudes_numdoc_index');
            }
        }

        // 3. pagos / pagos_propia
        $pagosTable = Schema::hasTable('pagos') ? 'pagos' : (Schema::hasTable('pagos_propia') ? 'pagos_propia' : null);
        if ($pagosTable !== null) {
            if ($this->hasColumn($pagosTable, 'dni') && !$this->hasColumn($pagosTable, 'numdoc')) {
                DB::statement("ALTER TABLE `{$pagosTable}` RENAME COLUMN `dni` TO `numdoc`");
                DB::statement("ALTER TABLE `{$pagosTable}` MODIFY COLUMN `numdoc` VARCHAR(32) DEFAULT NULL");
                $this->renameIndexIfExists($pagosTable, 'wdbi_dni_fecha_monto_pagado_efe9b6b35f35', 'wdbi_numdoc_fecha_monto_pagado_efe9b6b35f35');
                $this->renameIndexIfExists($pagosTable, 'wdbi_fecha_operacion_dni_monto_pagado_15dd9eed9eb1', 'wdbi_fecha_operacion_numdoc_monto_pagado_15dd9eed9eb1');
            }
        }

        // 4. promesas_pago
        if (Schema::hasTable('promesas_pago')) {
            if ($this->hasColumn('promesas_pago', 'dni') && !$this->hasColumn('promesas_pago', 'numdoc')) {
                DB::statement('ALTER TABLE `promesas_pago` RENAME COLUMN `dni` TO `numdoc`');
                DB::statement('ALTER TABLE `promesas_pago` MODIFY COLUMN `numdoc` VARCHAR(30) NOT NULL');
                $this->renameIndexIfExists('promesas_pago', 'promesas_pago_dni_index', 'promesas_pago_numdoc_index');
            }
        }
    }

    /**
     * Reverse the migrations: Revert numdoc back to dni.
     */
    public function down(): void
    {
        // 1. cliente_bloqueos
        if (Schema::hasTable('cliente_bloqueos')) {
            if ($this->hasColumn('cliente_bloqueos', 'numdoc') && !$this->hasColumn('cliente_bloqueos', 'dni')) {
                $this->renameIndexIfExists('cliente_bloqueos', 'cliente_bloqueos_user_id_numdoc_unique', 'cliente_bloqueos_user_id_dni_unique');
                $this->renameIndexIfExists('cliente_bloqueos', 'cliente_bloqueos_numdoc_index', 'cliente_bloqueos_dni_index');
                DB::statement('ALTER TABLE `cliente_bloqueos` MODIFY COLUMN `numdoc` VARCHAR(20) NOT NULL');
                DB::statement('ALTER TABLE `cliente_bloqueos` RENAME COLUMN `numdoc` TO `dni`');
            }
        }

        // 2. cna_solicitudes
        if (Schema::hasTable('cna_solicitudes')) {
            if ($this->hasColumn('cna_solicitudes', 'numdoc') && !$this->hasColumn('cna_solicitudes', 'dni')) {
                $this->renameIndexIfExists('cna_solicitudes', 'cna_solicitudes_numdoc_index', 'cna_solicitudes_dni_index');
                DB::statement('ALTER TABLE `cna_solicitudes` RENAME COLUMN `numdoc` TO `dni`');
            }
        }

        // 3. pagos / pagos_propia
        $pagosTable = Schema::hasTable('pagos') ? 'pagos' : (Schema::hasTable('pagos_propia') ? 'pagos_propia' : null);
        if ($pagosTable !== null) {
            if ($this->hasColumn($pagosTable, 'numdoc') && !$this->hasColumn($pagosTable, 'dni')) {
                $this->renameIndexIfExists($pagosTable, 'wdbi_numdoc_fecha_monto_pagado_efe9b6b35f35', 'wdbi_dni_fecha_monto_pagado_efe9b6b35f35');
                $this->renameIndexIfExists($pagosTable, 'wdbi_fecha_operacion_numdoc_monto_pagado_15dd9eed9eb1', 'wdbi_fecha_operacion_dni_monto_pagado_15dd9eed9eb1');
                DB::statement("ALTER TABLE `{$pagosTable}` RENAME COLUMN `numdoc` TO `dni`");
            }
        }

        // 4. promesas_pago
        if (Schema::hasTable('promesas_pago')) {
            if ($this->hasColumn('promesas_pago', 'numdoc') && !$this->hasColumn('promesas_pago', 'dni')) {
                $this->renameIndexIfExists('promesas_pago', 'promesas_pago_numdoc_index', 'promesas_pago_dni_index');
                DB::statement('ALTER TABLE `promesas_pago` RENAME COLUMN `numdoc` TO `dni`');
            }
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

    protected function renameIndexIfExists(string $table, string $oldName, string $newName): void
    {
        if ($this->indexExists($table, $oldName) && !$this->indexExists($table, $newName)) {
            DB::statement("ALTER TABLE `{$table}` RENAME INDEX `{$oldName}` TO `{$newName}`");
        }
    }
};
