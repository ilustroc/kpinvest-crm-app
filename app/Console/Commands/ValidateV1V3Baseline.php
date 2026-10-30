<?php

namespace App\Console\Commands;

use App\Support\Migrations\V1V3Baseline;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class ValidateV1V3Baseline extends Command
{
    protected $signature = 'baseline:preflight
        {--database= : Laravel connection name (uses the default connection when omitted)}
        {--schema= : Schema to inspect; required as a safeguard}';

    protected $description = 'Read-only V1 to V3 bootstrap compatibility preflight';

    /** @var array<string, array<string, string|array<int, string>>> */
    private const CRITICAL_COLUMNS = [
        'users' => [
            'id' => 'bigint(20) unsigned', 'email' => 'varchar(255)',
            'role' => "enum('administrador','supervisor','asesor','sistemas','soporte','usuario')",
            'supervisor_id' => 'bigint(20) unsigned', 'active' => 'tinyint(1)', 'is_active' => 'tinyint(1)',
        ],
        'clientes_cuentas' => [
            'id' => 'bigint(20) unsigned', 'numdoc' => 'varchar(30)', 'operacion' => 'varchar(50)',
            'deuda_total' => 'decimal(18,2)',
        ],
        'cliente_bloqueos' => ['id' => 'bigint(20) unsigned', 'user_id' => 'bigint(20) unsigned', 'dni' => 'varchar(20)'],
        'pagos_lotes' => ['id' => 'bigint(20) unsigned', 'tipo' => 'varchar(50)', 'usuario_id' => 'bigint(20) unsigned'],
        'pagos_propia' => [
            'id' => 'bigint(20) unsigned', 'lote_id' => 'bigint(20) unsigned', 'dni' => 'varchar(32)',
            'operacion' => 'varchar(64)', 'monto_pagado' => 'decimal(15,2)', 'fecha' => 'date',
        ],
        'promesas_pago' => [
            'id' => 'bigint(20) unsigned', 'dni' => 'varchar(30)', 'operacion' => ['varchar(50)', 'varchar(255)'],
            'workflow_estado' => 'varchar(20)', 'user_id' => 'bigint(20) unsigned',
        ],
        'promesa_cuotas' => ['id' => 'bigint(20) unsigned', 'promesa_id' => 'bigint(20) unsigned', 'monto' => 'decimal(12,2)'],
        'promesa_operaciones' => ['id' => 'bigint(20) unsigned', 'promesa_id' => 'bigint(20) unsigned', 'operacion' => 'varchar(50)'],
        'cna_solicitudes' => ['id' => 'bigint(20) unsigned', 'nro_carta' => 'varchar(255)', 'operaciones' => 'longtext'],
        'asignar_clientes' => ['id' => 'bigint(20) unsigned', 'numdoc' => 'varchar(20)', 'operacion' => 'varchar(50)'],
        'ccd_clientes' => ['id' => 'bigint(20) unsigned', 'numdoc' => 'varchar(12)', 'link' => 'varchar(500)'],
    ];

    /** @var array<string, array<string, array<int, string>>> */
    private const EXPECTED_INDEXES = [
        'users' => ['PRIMARY' => ['id'], 'users_email_unique' => ['email'], 'users_supervisor_id_idx' => ['supervisor_id']],
        'clientes_cuentas' => ['PRIMARY' => ['id'], 'idx_cc_numdoc_search' => ['numdoc', 'entidad', 'moneda', 'deuda_total', 'deuda_capital']],
        'cliente_bloqueos' => ['PRIMARY' => ['id'], 'cliente_bloqueos_user_id_dni_unique' => ['user_id', 'dni'], 'cliente_bloqueos_dni_index' => ['dni']],
        'pagos_lotes' => ['PRIMARY' => ['id']],
        'pagos_propia' => ['PRIMARY' => ['id'], 'fk_pagos_lote' => ['lote_id']],
        'promesas_pago' => [
            'PRIMARY' => ['id'], 'promesas_pago_user_id_foreign' => ['user_id'],
            'promesas_pago_dni_index' => ['dni'], 'promesas_pago_operacion_index' => ['operacion'],
            'promesas_pago_pre_aprobado_por_foreign' => ['pre_aprobado_por'],
            'promesas_pago_aprobado_por_foreign' => ['aprobado_por'],
            'promesas_pago_rechazado_por_foreign' => ['rechazado_por'],
            'promesas_pago_workflow_estado_index' => ['workflow_estado'],
        ],
        'promesa_cuotas' => ['PRIMARY' => ['id'], 'promesa_cuotas_promesa_id_nro_index' => ['promesa_id', 'nro']],
        'promesa_operaciones' => [
            'PRIMARY' => ['id'], 'promesa_operaciones_promesa_id_operacion_unique' => ['promesa_id', 'operacion'],
            'promesa_operaciones_operacion_index' => ['operacion'], 'promesa_operaciones_cartera_index' => ['cartera'],
        ],
        'cna_solicitudes' => ['PRIMARY' => ['id'], 'cna_solicitudes_nro_carta_unique' => ['nro_carta'], 'cna_solicitudes_dni_index' => ['dni']],
        'asignar_clientes' => [
            'PRIMARY' => ['id'], 'unique_asignacion_cliente' => ['numdoc', 'operacion'],
            'asignar_clientes_numdoc_index' => ['numdoc'], 'asignar_clientes_operacion_index' => ['operacion'],
        ],
        'ccd_clientes' => ['PRIMARY' => ['id']],
    ];

    /** @var array<string, array<int, array{0: string, 1: string, 2: string}>> */
    private const EXPECTED_FOREIGN_KEYS = [
        'cliente_bloqueos' => [['user_id', 'users', 'id']],
        'pagos_propia' => [['lote_id', 'pagos_lotes', 'id']],
        'promesas_pago' => [
            ['user_id', 'users', 'id'], ['pre_aprobado_por', 'users', 'id'],
            ['aprobado_por', 'users', 'id'], ['rechazado_por', 'users', 'id'],
        ],
        'promesa_cuotas' => [['promesa_id', 'promesas_pago', 'id']],
        'promesa_operaciones' => [['promesa_id', 'promesas_pago', 'id']],
    ];

    /** @var array<string, string> */
    private const HISTORICAL_TABLES = [
        '2019_12_14_000001_create_personal_access_tokens_table' => 'personal_access_tokens',
        '2025_08_15_164655_create_pagos_lotes_table' => 'pagos_lotes',
        '2025_08_15_164655_create_pagos_propia_table' => 'pagos_propia',
        '2025_08_15_192105_create_pagos_caja_cusco_castigada_table' => 'pagos_caja_cusco_castigada',
        '2025_11_13_155533_create_asignar_clientes_table' => 'asignar_clientes',
        '2026_01_12_221108_create_cliente_bloqueos_table' => 'cliente_bloqueos',
    ];

    private int $errors = 0;
    private int $warnings = 0;

    public function handle(): int
    {
        $this->errors = 0;
        $this->warnings = 0;

        $schema = (string) $this->option('schema');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $schema)) {
            $this->error('Debe indicar --schema=<nombre_seguro>; el comando no infiere ni cambia la base de datos.');

            return self::FAILURE;
        }

        $connection = DB::connection($this->option('database') ?: null);

        if ($connection->getDatabaseName() !== $schema) {
            $this->error(sprintf('La conexión apunta a "%s", no a "%s". No se realizó ninguna consulta de auditoría.', $connection->getDatabaseName(), $schema));

            return self::FAILURE;
        }

        $this->info("Preflight V1 → V3 (solo lectura): {$schema}");

        $this->validateBootstrapTables($connection, $schema);
        $this->validateUsers($connection, $schema);
        $this->validatePaymentsAndNotifications($connection, $schema);
        $this->validateMigrationHistory($connection, $schema);
        $this->line('Diferencias permitidas: promesas_pago.operacion varchar(255) (V3 nuevo usa varchar(50)) e índices históricos adicionales; no se eliminan ni se alteran.');
        $this->reportWarning('Bloqueo de política: 000007 y 121500 no deben ejecutarse ni registrarse como baseline sin una política de roles aprobada.');
        $this->line('Baseline propuesto: registrar únicamente 000001–000006 mediante una utilidad Laravel auditada posterior a este preflight; conservar 000007 y 121500 pendientes/sustituidos por una reconciliación explícita aprobada.');

        if ($this->errors > 0) {
            $this->error("Resultado: INCOMPATIBLE ({$this->errors} error(es), {$this->warnings} advertencia(s)).");

            return self::FAILURE;
        }

        $this->info("Resultado: COMPATIBLE PARA BASELINE BOOTSTRAP ({$this->warnings} advertencia(s), ninguna escritura).");

        return self::SUCCESS;
    }

    private function validateBootstrapTables(ConnectionInterface $connection, string $schema): void
    {
        $this->line('Bootstrap V3 000001–000006:');

        foreach (self::CRITICAL_COLUMNS as $table => $expectedColumns) {
            if (! $this->tableExists($connection, $schema, $table)) {
                $this->fail("Falta la tabla requerida {$table}.");
                continue;
            }

            $columns = collect($connection->select(
                'SELECT column_name, column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
                [$schema, $table]
            ))->keyBy('column_name');

            foreach ($expectedColumns as $column => $expected) {
                $actual = strtolower($columns->get($column)->column_type ?? '');
                $allowed = array_map('strtolower', (array) $expected);

                if ($actual === '') {
                    $this->fail("{$table}.{$column} no existe.");
                } elseif (! in_array($actual, $allowed, true)) {
                    $this->fail("{$table}.{$column} es {$actual}; se esperaba ".implode(' o ', $allowed).'.');
                } elseif ($table === 'promesas_pago' && $column === 'operacion' && $actual === 'varchar(255)') {
                    $this->reportWarning('promesas_pago.operacion es varchar(255), diferencia histórica permitida frente a varchar(50).');
                }
            }

            $this->validateIndexes($connection, $schema, $table);
            $this->validateForeignKey($connection, $schema, $table);
            $this->info("  ✓ {$table}");
        }
    }

    private function validateIndexes(ConnectionInterface $connection, string $schema, string $table): void
    {
        $indexes = collect($connection->select(
            'SELECT index_name, column_name, seq_in_index FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? ORDER BY index_name, seq_in_index',
            [$schema, $table]
        ))->groupBy('index_name')->map(fn ($rows) => $rows->pluck('column_name')->all());

        foreach (self::EXPECTED_INDEXES[$table] ?? [] as $name => $columns) {
            if ($indexes->get($name) !== $columns) {
                $this->fail("{$table}.{$name} no coincide o falta.");
            }
        }

        $extra = $indexes->keys()->diff(array_keys(self::EXPECTED_INDEXES[$table] ?? []))->filter();
        if ($extra->isNotEmpty()) {
            $this->line("    · índices históricos adicionales permitidos: {$extra->implode(', ')}");
        }
    }

    private function validateForeignKey(ConnectionInterface $connection, string $schema, string $table): void
    {
        if (! isset(self::EXPECTED_FOREIGN_KEYS[$table])) {
            return;
        }

        foreach (self::EXPECTED_FOREIGN_KEYS[$table] as [$column, $referencedTable, $referencedColumn]) {
            $exists = $connection->selectOne(
                'SELECT 1 FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name = ? AND referenced_column_name = ? LIMIT 1',
                [$schema, $table, $column, $referencedTable, $referencedColumn]
            );

            if (! $exists) {
                $this->fail("{$table}.{$column} no tiene la clave foránea esperada hacia {$referencedTable}.{$referencedColumn}.");
            }
        }
    }

    private function validateUsers(ConnectionInterface $connection, string $schema): void
    {
        if (! $this->tableExists($connection, $schema, 'users')) {
            return;
        }

        $role = $connection->selectOne(
            'SELECT column_type, column_default FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$schema, 'users', 'role']
        );
        $distribution = $connection->select('SELECT role, COUNT(*) AS total FROM users GROUP BY role ORDER BY role');
        $roles = collect($distribution)->pluck('total', 'role');

        $this->line('Users: default role='.($role->column_default ?? 'N/D').'; distribución='.$roles->map(fn ($count, $name) => "{$name}:{$count}")->implode(', ').'.');

        if ($roles->has('sistemas') || $roles->has('usuario')) {
            $this->reportWarning('Se detectaron roles históricos sistemas/usuario: no deben convertirse automáticamente a soporte.');
        }

        if (($role->column_default ?? null) === 'soporte') {
            $this->reportWarning('El default actual ya es soporte; confirme política de altas antes de cualquier baseline.');
        }
    }

    private function validatePaymentsAndNotifications(ConnectionInterface $connection, string $schema): void
    {
        $hasPagosPropia = $this->tableExists($connection, $schema, 'pagos_propia');
        $hasPagos = $this->tableExists($connection, $schema, 'pagos');
        $paymentCount = $hasPagosPropia ? (int) $connection->selectOne('SELECT COUNT(*) AS total FROM pagos_propia')->total : 0;

        if (! $hasPagosPropia || $hasPagos) {
            $this->fail('Estado de pagos incompatible: se requiere pagos_propia presente y pagos ausente antes del renombrado V3.');
        } else {
            $this->info("Pagos: pagos_propia presente ({$paymentCount} registros); pagos ausente.");
        }

        if ($this->tableExists($connection, $schema, 'notifications')) {
            $this->reportWarning('notifications ya existe; la migración V3 2026_05_21_122013 debe revisarse antes de registrarse.');
        } else {
            $this->info('Notifications: ausente; la migración V3 2026_05_21_122013 puede crearla.');
        }
    }

    private function validateMigrationHistory(ConnectionInterface $connection, string $schema): void
    {
        if (! $this->tableExists($connection, $schema, 'migrations')) {
            $this->fail('Falta la tabla migrations histórica.');
            return;
        }

        $migrations = collect($connection->select('SELECT migration, batch FROM migrations ORDER BY batch, migration'));
        $registeredBootstrap = $migrations->pluck('migration')->intersect(V1V3Baseline::BOOTSTRAP_MIGRATIONS);

        if (! in_array($registeredBootstrap->count(), [0, count(V1V3Baseline::BOOTSTRAP_MIGRATIONS)], true)) {
            $this->fail('El bootstrap V3 está parcialmente registrado en migrations.');
        }

        foreach (self::HISTORICAL_TABLES as $migration => $table) {
            if ($migrations->contains('migration', $migration) && ! $this->tableExists($connection, $schema, $table)) {
                $this->reportWarning("Historial {$migration} registrado, pero la tabla {$table} no existe.");
            }
        }

        $bootstrapState = $registeredBootstrap->isEmpty() ? 'sin registrar' : 'registrado completamente';
        $this->line('Historial: '.$migrations->count()." migraciones; bootstrap V3 000001–000006 {$bootstrapState}.");
    }

    private function tableExists(ConnectionInterface $connection, string $schema, string $table): bool
    {
        return (bool) $connection->selectOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? AND table_type = \'BASE TABLE\' LIMIT 1',
            [$schema, $table]
        );
    }

    private function fail(string $message): void
    {
        $this->errors++;
        $this->error("  ✗ {$message}");
    }

    private function reportWarning(string $message): void
    {
        $this->warnings++;
        $this->warn("  ! {$message}");
    }
}
