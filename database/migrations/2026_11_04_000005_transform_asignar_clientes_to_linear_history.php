<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transform asignar_clientes into a linear assignment history table
     * supporting manual requests, approval workflows, and bulk imports.
     */
    public function up(): void
    {
        if (!Schema::hasTable('asignar_clientes')) {
            return;
        }

        // 1. Widen numdoc to VARCHAR(30) and make legacy name nullable
        if ($this->hasColumn('asignar_clientes', 'numdoc')) {
            DB::statement('ALTER TABLE `asignar_clientes` MODIFY COLUMN `numdoc` VARCHAR(30) NOT NULL');
        }

        if ($this->hasColumn('asignar_clientes', 'name')) {
            DB::statement('ALTER TABLE `asignar_clientes` MODIFY COLUMN `name` VARCHAR(150) DEFAULT NULL');
        }

        // 2. Drop UNIQUE (numdoc, operacion) so multiple historical requests can exist per account
        if ($this->indexExists('asignar_clientes', 'unique_asignacion_cliente')) {
            DB::statement('ALTER TABLE `asignar_clientes` DROP INDEX `unique_asignacion_cliente`');
        }

        // 3. Add linear history columns
        $columnsToAdd = [
            'cliente_cuenta_id'      => 'ADD COLUMN `cliente_cuenta_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `operacion`',
            'solicitante_user_id'    => 'ADD COLUMN `solicitante_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `name`',
            'asesor_destino_user_id' => 'ADD COLUMN `asesor_destino_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `solicitante_user_id`',
            'fecha_solicitud'        => 'ADD COLUMN `fecha_solicitud` DATETIME DEFAULT NULL AFTER `asesor_destino_user_id`',
            'motivo'                 => 'ADD COLUMN `motivo` TEXT DEFAULT NULL AFTER `fecha_solicitud`',
            'estado'                 => "ADD COLUMN `estado` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'aprobada' AFTER `motivo`",
            'aprobado_por_user_id'   => 'ADD COLUMN `aprobado_por_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `estado`',
            'fecha_aprobacion'       => 'ADD COLUMN `fecha_aprobacion` DATETIME DEFAULT NULL AFTER `aprobado_por_user_id`',
            'observacion_resolucion' => 'ADD COLUMN `observacion_resolucion` TEXT DEFAULT NULL AFTER `fecha_aprobacion`',
            'origen'                 => "ADD COLUMN `origen` VARCHAR(30) NOT NULL DEFAULT 'carga_masiva' AFTER `observacion_resolucion`",
            'created_at'             => 'ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() AFTER `origen`',
            'updated_at'             => 'ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() AFTER `created_at`',
        ];

        $alterParts = [];
        foreach ($columnsToAdd as $col => $sqlPart) {
            if (!$this->hasColumn('asignar_clientes', $col)) {
                $alterParts[] = $sqlPart;
            }
        }

        if (!empty($alterParts)) {
            DB::statement('ALTER TABLE `asignar_clientes` ' . implode(', ', $alterParts));
        }

        // 4. Add composite indexes for fast vigente resolution and history queries
        $indexes = [
            'asignar_clientes_numdoc_operacion_vigente_idx' => '(`numdoc`, `operacion`, `estado`, `fecha_solicitud`, `id`)',
            'asignar_clientes_cuenta_vigente_idx'           => '(`cliente_cuenta_id`, `estado`, `fecha_solicitud`, `id`)',
            'asignar_clientes_estado_fecha_idx'             => '(`estado`, `fecha_solicitud`, `id`)',
            'asignar_clientes_solicitante_user_id_idx'      => '(`solicitante_user_id`)',
            'asignar_clientes_asesor_destino_user_id_idx'   => '(`asesor_destino_user_id`)',
            'asignar_clientes_aprobado_por_user_id_idx'     => '(`aprobado_por_user_id`)',
        ];

        foreach ($indexes as $indexName => $columnsDef) {
            if (!$this->indexExists('asignar_clientes', $indexName)) {
                DB::statement("ALTER TABLE `asignar_clientes` ADD INDEX `{$indexName}` {$columnsDef}");
            }
        }

        // 5. Add Foreign Keys (ON DELETE SET NULL to preserve history if a user/account is removed)
        $foreignKeys = [
            'asignar_clientes_cliente_cuenta_id_foreign' => [
                'col' => 'cliente_cuenta_id',
                'refTable' => 'clientes_cuentas',
                'refCol' => 'id',
            ],
            'asignar_clientes_solicitante_user_id_foreign' => [
                'col' => 'solicitante_user_id',
                'refTable' => 'users',
                'refCol' => 'id',
            ],
            'asignar_clientes_asesor_destino_user_id_foreign' => [
                'col' => 'asesor_destino_user_id',
                'refTable' => 'users',
                'refCol' => 'id',
            ],
            'asignar_clientes_aprobado_por_user_id_foreign' => [
                'col' => 'aprobado_por_user_id',
                'refTable' => 'users',
                'refCol' => 'id',
            ],
        ];

        foreach ($foreignKeys as $fkName => $fk) {
            if (Schema::hasTable($fk['refTable']) && !$this->foreignKeyExists('asignar_clientes', $fkName)) {
                DB::statement(
                    "ALTER TABLE `asignar_clientes` ADD CONSTRAINT `{$fkName}` " .
                    "FOREIGN KEY (`{$fk['col']}`) REFERENCES `{$fk['refTable']}` (`{$fk['refCol']}`) ON DELETE SET NULL"
                );
            }
        }

        // 6. Backfill unambiguous historical references without inventing users
        if (Schema::hasTable('users')) {
            // Only map where normalized user name is unique in users table
            DB::statement("
                UPDATE `asignar_clientes` ac
                JOIN (
                    SELECT MIN(t.id) AS user_id, t.norm_name
                    FROM (
                        SELECT id, TRIM(UPPER(REPLACE(name, UNHEX('C2A0'), ' '))) AS norm_name
                        FROM `users`
                    ) t
                    GROUP BY t.norm_name
                    HAVING COUNT(*) = 1
                ) u ON TRIM(UPPER(REPLACE(ac.name, UNHEX('C2A0'), ' '))) = u.norm_name
                SET ac.asesor_destino_user_id = u.user_id
                WHERE ac.asesor_destino_user_id IS NULL AND ac.name IS NOT NULL AND ac.name != ''
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('asignar_clientes')) {
            return;
        }

        // 1. Drop foreign keys
        $fks = [
            'asignar_clientes_cliente_cuenta_id_foreign',
            'asignar_clientes_solicitante_user_id_foreign',
            'asignar_clientes_asesor_destino_user_id_foreign',
            'asignar_clientes_aprobado_por_user_id_foreign',
        ];

        foreach ($fks as $fkName) {
            if ($this->foreignKeyExists('asignar_clientes', $fkName)) {
                DB::statement("ALTER TABLE `asignar_clientes` DROP FOREIGN KEY `{$fkName}`");
            }
        }

        // 2. Drop added indexes
        $indexes = [
            'asignar_clientes_numdoc_operacion_vigente_idx',
            'asignar_clientes_cuenta_vigente_idx',
            'asignar_clientes_estado_fecha_idx',
            'asignar_clientes_solicitante_user_id_idx',
            'asignar_clientes_asesor_destino_user_id_idx',
            'asignar_clientes_aprobado_por_user_id_idx',
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists('asignar_clientes', $indexName)) {
                DB::statement("ALTER TABLE `asignar_clientes` DROP INDEX `{$indexName}`");
            }
        }

        // 3. Drop added columns
        $columnsToDrop = [
            'cliente_cuenta_id',
            'solicitante_user_id',
            'asesor_destino_user_id',
            'fecha_solicitud',
            'motivo',
            'estado',
            'aprobado_por_user_id',
            'fecha_aprobacion',
            'observacion_resolucion',
            'origen',
            'created_at',
            'updated_at',
        ];

        $dropParts = [];
        foreach ($columnsToDrop as $col) {
            if ($this->hasColumn('asignar_clientes', $col)) {
                $dropParts[] = "DROP COLUMN `{$col}`";
            }
        }

        if (!empty($dropParts)) {
            DB::statement('ALTER TABLE `asignar_clientes` ' . implode(', ', $dropParts));
        }

        // 4. Restore name NOT NULL
        if ($this->hasColumn('asignar_clientes', 'name')) {
            DB::statement("UPDATE `asignar_clientes` SET `name` = '' WHERE `name` IS NULL");
            DB::statement('ALTER TABLE `asignar_clientes` MODIFY COLUMN `name` VARCHAR(150) NOT NULL');
        }

        // 5. Restore unique index if no duplicate (numdoc, operacion) pairs exist
        if (!$this->indexExists('asignar_clientes', 'unique_asignacion_cliente')) {
            $hasDuplicates = DB::table('asignar_clientes')
                ->select('numdoc', 'operacion')
                ->groupBy('numdoc', 'operacion')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if (!$hasDuplicates) {
                DB::statement('ALTER TABLE `asignar_clientes` ADD UNIQUE INDEX `unique_asignacion_cliente` (`numdoc`, `operacion`)');
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

    protected function foreignKeyExists(string $table, string $constraintName): bool
    {
        $database = DB::connection()->getDatabaseName();
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
