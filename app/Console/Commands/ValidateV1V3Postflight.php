<?php

namespace App\Console\Commands;

use App\Support\Migrations\V1V3Baseline;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ValidateV1V3Postflight extends Command
{
    protected $signature = 'baseline:postflight
        {--database= : Laravel connection name (uses the default connection when omitted)}
        {--schema= : Schema to inspect; required as a safeguard}
        {--baseline-file= : Optional path to a JSON file with pre-migration baseline metrics to compare against}';

    protected $description = 'Read-only post-migration verification for KP Invest V1 to V3';

    public const EXPECTED_TABLES = [
        'asignar_clientes',
        'cliente_bloqueos',
        'clientes_cuentas',
        'ccd_clientes',
        'cna_solicitudes',
        'migrations',
        'notifications',
        'pagos_lotes',
        'pagos',
        'personal_access_tokens',
        'promesa_cuotas',
        'promesa_operaciones',
        'promesas_pago',
        'users',
    ];

    public const EXPECTED_MIGRATIONS = [
        // Batch 1 (Bootstrap 000001-000006 + personal_access_tokens)
        '2019_12_14_000001_create_personal_access_tokens_table',
        '2026_05_15_000001_create_users_table',
        '2026_05_15_000002_create_clientes_tables',
        '2026_05_15_000003_create_pagos_tables',
        '2026_05_15_000004_create_promesas_tables',
        '2026_05_15_000005_create_cna_solicitudes_table',
        '2026_05_15_000006_create_integracion_tables',
        // Batches 2-7 (Histórico V1 intacto)
        '2025_08_15_164655_create_pagos_lotes_table',
        '2025_08_15_164655_create_pagos_propia_table',
        '2025_08_15_192105_create_pagos_caja_cusco_castigada_table',
        '2025_11_02_220020_update_users_drop_team_supervisor_add_active',
        '2025_11_05_172046_add_supervisor_id_to_users',
        '2025_11_07_224706_agregar_link_descarga',
        '2025_11_13_155533_create_asignar_clientes_table',
        '2026_01_12_221108_create_cliente_bloqueos_table',
        '2026_01_19_202218_drop_cumplimiento_estado_from_promesas_pago',
        // Batch 8 (Incrementales V3 aplicadas)
        '2026_05_15_000007_normalize_user_roles_v3',
        '2026_05_21_122013_create_notifications_table',
        '2026_10_29_121500_update_users_drop_is_active_and_normalize_roles',
        '2026_10_29_123000_rename_pagos_propia_to_pagos',
    ];

    private const EXPECTED_PAGOS_COLUMNS = [
        'id' => 'bigint(20) unsigned',
        'lote_id' => 'bigint(20) unsigned',
        'dni' => 'varchar(32)',
        'operacion' => 'varchar(64)',
        'entidad' => 'varchar(150)',
        'nombre_cliente' => 'varchar(255)',
        'monto_pagado' => 'decimal(15,2)',
        'fecha' => 'date',
        'gestor' => 'varchar(120)',
        'cosecha' => 'varchar(100)',
        'cuenta_recaudo' => 'varchar(100)',
    ];

    private const EXPECTED_NOTIFICATIONS_COLUMNS = [
        'id' => 'char(36)',
        'type' => 'varchar(255)',
        'notifiable_type' => 'varchar(255)',
        'notifiable_id' => 'bigint(20) unsigned',
        'data' => ['text', 'longtext', 'json'],
        'read_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
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

        // 1. Confirmar el esquema de conexión efectivo
        if ($connection->getDatabaseName() !== $schema) {
            $this->error(sprintf(
                'La conexión efectiva apunta a "%s", no a "%s". Abortado sin consultar.',
                $connection->getDatabaseName(),
                $schema
            ));

            return self::FAILURE;
        }

        $this->info("Postflight V1 → V3 (solo lectura): {$schema}");

        // 2. Verificar las 14 tablas esperadas
        $this->validateExpectedTables($connection, $schema);

        // 3. Confirmar que las 20 migraciones esperadas están registradas
        $this->validateMigrationHistory($connection, $schema);

        // 4. Confirmar que existe pagos y no pagos_propia
        $this->validatePaymentsTableState($connection, $schema);

        // 5. Validar columnas, índices y claves foráneas críticas de pagos
        $this->validatePaymentsStructure($connection, $schema);

        // 6. Confirmar estructura de users (active, sin is_active, role sin DEFAULT)
        $this->validateUsersStructure($connection, $schema);

        // 7. Confirmar estructura de notifications
        $this->validateNotificationsStructure($connection, $schema);

        // 8. Verificar que promesas_pago.operacion conserva VARCHAR(255)
        $this->validatePromesasPagoOperation($connection, $schema);

        // 9. Detectar referencias huérfanas en las relaciones críticas
        $this->validateOrphanReferences($connection, $schema);

        // 10. Informar conteos y sumas agregadas sin mostrar información personal
        $this->reportAggregateMetrics($connection, $schema);

        if ($this->errors > 0) {
            $this->error("Resultado: BLOQUEADO ({$this->errors} error(es), {$this->warnings} advertencia(s)).");

            return self::FAILURE;
        }

        $this->info("Resultado: APROBADO (0 error(es), {$this->warnings} advertencia(s), solo lectura).");

        return self::SUCCESS;
    }

    private function validateExpectedTables(ConnectionInterface $connection, string $schema): void
    {
        $this->line('1. Verificación de tablas del esquema:');

        $tables = collect($connection->select(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = \'BASE TABLE\'',
            [$schema]
        ))->pluck('table_name')->map('strtolower');

        $missing = collect(self::EXPECTED_TABLES)->diff($tables);
        if ($missing->isNotEmpty()) {
            $this->fail('Faltan tablas requeridas: ' . $missing->implode(', '));
        } else {
            $this->info('  ✓ Las 14 tablas esperadas existen en el esquema.');
        }

        if ($tables->contains('pagos_propia')) {
            $this->fail('La tabla obsoleta pagos_propia sigue existiendo en el esquema.');
        }

        $unexpected = $tables->diff(self::EXPECTED_TABLES);
        if ($unexpected->isNotEmpty()) {
            $this->reportWarning('Tablas no listadas en el estándar V3: ' . $unexpected->implode(', '));
        }
    }

    private function validateMigrationHistory(ConnectionInterface $connection, string $schema): void
    {
        $this->line('2. Verificación del historial de migraciones:');

        if (! $this->tableExists($connection, $schema, 'migrations')) {
            $this->fail('La tabla migrations no existe.');
            return;
        }

        $migrations = collect($connection->select(
            'SELECT migration, batch FROM migrations ORDER BY batch, migration'
        ));

        // Duplicados
        $duplicates = $migrations->groupBy('migration')->filter(fn ($group) => $group->count() > 1)->keys();
        if ($duplicates->isNotEmpty()) {
            $this->fail('Migraciones duplicadas en tabla migrations: ' . $duplicates->implode(', '));
        }

        $registeredNames = $migrations->pluck('migration');
        $expectedCount = count(self::EXPECTED_MIGRATIONS);
        $actualCount = $registeredNames->count();

        if ($actualCount < $expectedCount) {
            $this->fail("Se esperaban al menos {$expectedCount} migraciones registradas (conjunto base obligatorio V3); se encontraron {$actualCount}.");
        }

        $missing = collect(self::EXPECTED_MIGRATIONS)->diff($registeredNames);
        if ($missing->isNotEmpty()) {
            $this->fail('Migraciones obligatorias V3 no registradas: ' . $missing->implode(', '));
        }

        $unexpected = $registeredNames->diff(self::EXPECTED_MIGRATIONS);
        if ($unexpected->isNotEmpty()) {
            $this->info('  · Migraciones adicionales posteriores al baseline V3: ' . $unexpected->implode(', '));
        }

        // Revisar si existen archivos de migración en disco no aplicados
        $diskFiles = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($path) => basename($path, '.php'));

        $pending = $diskFiles->diff($registeredNames);
        if ($pending->isNotEmpty()) {
            $this->fail('Migraciones en disco pendientes de ejecución: ' . $pending->implode(', '));
        }

        if ($missing->isEmpty() && $duplicates->isEmpty() && $pending->isEmpty() && $actualCount >= $expectedCount) {
            $this->info("  ✓ Historial íntegro: {$actualCount} migraciones registradas (incluye las {$expectedCount} obligatorias V3), 0 duplicados, 0 pendientes.");
        }
    }

    private function validatePaymentsTableState(ConnectionInterface $connection, string $schema): void
    {
        $this->line('3. Estado de tablas de pagos:');

        $hasPagos = $this->tableExists($connection, $schema, 'pagos');
        $hasPagosPropia = $this->tableExists($connection, $schema, 'pagos_propia');

        if (! $hasPagos) {
            $this->fail('La tabla activa pagos no existe.');
        } else {
            $this->info('  ✓ Tabla pagos presente.');
        }

        if ($hasPagosPropia) {
            $this->fail('La tabla antigua pagos_propia aún existe (no se renombró correctamente).');
        } else {
            $this->info('  ✓ Tabla obsoleta pagos_propia inexistente.');
        }
    }

    private function validatePaymentsStructure(ConnectionInterface $connection, string $schema): void
    {
        $this->line('4. Estructura e integridad de la tabla pagos:');

        if (! $this->tableExists($connection, $schema, 'pagos')) {
            $this->fail('No es posible validar la estructura de pagos porque la tabla no existe.');
            return;
        }

        $columns = collect($connection->select(
            'SELECT column_name, column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
            [$schema, 'pagos']
        ))->keyBy('column_name');

        foreach (self::EXPECTED_PAGOS_COLUMNS as $col => $expectedType) {
            $actual = strtolower($columns->get($col)->column_type ?? '');
            if ($actual === '') {
                $this->fail("pagos.{$col} no existe.");
            } elseif ($actual !== strtolower($expectedType)) {
                $this->fail("pagos.{$col} es {$actual}; se esperaba {$expectedType}.");
            }
        }

        // Validar clave foránea a pagos_lotes(id)
        $fk = $connection->selectOne(
            'SELECT constraint_name, referenced_table_name, referenced_column_name FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name = ? AND referenced_column_name = ? LIMIT 1',
            [$schema, 'pagos', 'lote_id', 'pagos_lotes', 'id']
        );

        if (! $fk) {
            $this->fail('pagos.lote_id no posee la clave foránea hacia pagos_lotes.id.');
        } else {
            $this->info('  ✓ Clave foránea pagos.lote_id -> pagos_lotes.id verificada.');
        }

        // Validar índice en lote_id
        $hasIndex = (bool) $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$schema, 'pagos', 'lote_id']
        );

        if (! $hasIndex) {
            $this->fail('pagos.lote_id no está indexado.');
        } else {
            $this->info('  ✓ Índices críticos de pagos verificados.');
        }
    }

    private function validateUsersStructure(ConnectionInterface $connection, string $schema): void
    {
        $this->line('5. Estructura e integridad de users:');

        if (! $this->tableExists($connection, $schema, 'users')) {
            $this->fail('La tabla users no existe.');
            return;
        }

        $columns = collect($connection->select(
            'SELECT column_name, column_type, column_default, is_nullable FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
            [$schema, 'users']
        ))->keyBy('column_name');

        // active
        $activeCol = $columns->get('active');
        if (! $activeCol) {
            $this->fail('users.active no existe.');
        } elseif (strtolower($activeCol->column_type) !== 'tinyint(1)') {
            $this->fail("users.active es {$activeCol->column_type}; se esperaba tinyint(1).");
        } else {
            $this->info('  ✓ users.active presente (tinyint(1)).');
        }

        // is_active
        if ($columns->has('is_active')) {
            $this->fail('users.is_active sigue existiendo; debe eliminarse en V3.');
        } else {
            $this->info('  ✓ users.is_active eliminado.');
        }

        // role
        $roleCol = $columns->get('role');
        $expectedRoleType = "enum('administrador','supervisor','asesor','soporte')";

        if (! $roleCol) {
            $this->fail('users.role no existe.');
        } else {
            if (strtolower($roleCol->column_type) !== strtolower($expectedRoleType)) {
                $this->fail("users.role es {$roleCol->column_type}; se esperaba {$expectedRoleType}.");
            } else {
                $this->info('  ✓ users.role restringido exactamente a los cuatro roles autorizados.');
            }

            if ($roleCol->column_default !== null) {
                $this->fail("users.role tiene default '{$roleCol->column_default}'; debe ser NULL (sin default).");
            } else {
                $this->info('  ✓ users.role no tiene DEFAULT (exige rol explícito).');
            }
        }

        // Verificar datos reales en users sin mostrar información personal
        $rows = $connection->select('SELECT role, active, COUNT(*) AS total FROM users GROUP BY role, active ORDER BY role, active');
        $invalidRoles = collect($rows)->pluck('role')->diff(['administrador', 'supervisor', 'asesor', 'soporte']);

        if ($invalidRoles->isNotEmpty()) {
            $this->fail('Se encontraron registros con roles no autorizados: ' . $invalidRoles->implode(', '));
        }
    }

    private function validateNotificationsStructure(ConnectionInterface $connection, string $schema): void
    {
        $this->line('6. Estructura de notifications:');

        if (! $this->tableExists($connection, $schema, 'notifications')) {
            $this->fail('La tabla notifications no existe.');
            return;
        }

        $columns = collect($connection->select(
            'SELECT column_name, column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
            [$schema, 'notifications']
        ))->keyBy('column_name');

        foreach (self::EXPECTED_NOTIFICATIONS_COLUMNS as $col => $expectedType) {
            $actual = strtolower($columns->get($col)->column_type ?? '');
            $allowed = array_map('strtolower', (array) $expectedType);
            if ($actual === '') {
                $this->fail("notifications.{$col} no existe.");
            } elseif (! in_array($actual, $allowed, true)) {
                $this->fail("notifications.{$col} es {$actual}; se esperaba " . implode(' o ', $allowed) . '.');
            }
        }

        $hasPk = (bool) $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = \'PRIMARY\' AND column_name = \'id\' LIMIT 1',
            [$schema, 'notifications']
        );

        if (! $hasPk) {
            $this->fail('notifications no posee clave primaria en id.');
        } else {
            $this->info('  ✓ notifications validada (UUID, campos polimórficos e índices).');
        }
    }

    private function validatePromesasPagoOperation(ConnectionInterface $connection, string $schema): void
    {
        $this->line('7. Validación de promesas_pago.operacion:');

        if (! $this->tableExists($connection, $schema, 'promesas_pago')) {
            $this->fail('La tabla promesas_pago no existe.');
            return;
        }

        $col = $connection->selectOne(
            'SELECT column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$schema, 'promesas_pago', 'operacion']
        );

        if (! $col) {
            $this->fail('promesas_pago.operacion no existe.');
        } elseif (strtolower($col->column_type) !== 'varchar(255)') {
            $this->fail("promesas_pago.operacion es {$col->column_type}; debe conservar varchar(255).");
        } else {
            $this->info('  ✓ promesas_pago.operacion conserva VARCHAR(255).');
        }
    }

    private function validateOrphanReferences(ConnectionInterface $connection, string $schema): void
    {
        $this->line('8. Detección de referencias huérfanas en relaciones críticas:');

        $orphanChecks = [
            'pagos.lote_id -> pagos_lotes.id' => [
                'table' => 'pagos',
                'refTable' => 'pagos_lotes',
                'sql' => 'SELECT COUNT(*) AS total FROM pagos p LEFT JOIN pagos_lotes l ON p.lote_id = l.id WHERE p.lote_id IS NOT NULL AND l.id IS NULL',
            ],
            'users.supervisor_id -> users.id' => [
                'table' => 'users',
                'refTable' => 'users',
                'sql' => 'SELECT COUNT(*) AS total FROM users u LEFT JOIN users s ON u.supervisor_id = s.id WHERE u.supervisor_id IS NOT NULL AND s.id IS NULL',
            ],
            'promesas_pago.user_id -> users.id' => [
                'table' => 'promesas_pago',
                'refTable' => 'users',
                'sql' => 'SELECT COUNT(*) AS total FROM promesas_pago p LEFT JOIN users u ON p.user_id = u.id WHERE p.user_id IS NOT NULL AND u.id IS NULL',
            ],
            'promesa_cuotas.promesa_id -> promesas_pago.id' => [
                'table' => 'promesa_cuotas',
                'refTable' => 'promesas_pago',
                'sql' => 'SELECT COUNT(*) AS total FROM promesa_cuotas c LEFT JOIN promesas_pago p ON c.promesa_id = p.id WHERE p.id IS NULL',
            ],
            'promesa_operaciones.promesa_id -> promesas_pago.id' => [
                'table' => 'promesa_operaciones',
                'refTable' => 'promesas_pago',
                'sql' => 'SELECT COUNT(*) AS total FROM promesa_operaciones o LEFT JOIN promesas_pago p ON o.promesa_id = p.id WHERE p.id IS NULL',
            ],
            'cliente_bloqueos.user_id -> users.id' => [
                'table' => 'cliente_bloqueos',
                'refTable' => 'users',
                'sql' => 'SELECT COUNT(*) AS total FROM cliente_bloqueos b LEFT JOIN users u ON b.user_id = u.id WHERE b.user_id IS NOT NULL AND u.id IS NULL',
            ],
        ];

        foreach ($orphanChecks as $label => $check) {
            if (! $this->tableExists($connection, $schema, $check['table']) || ! $this->tableExists($connection, $schema, $check['refTable'])) {
                $this->fail("No se pudo evaluar relación {$label}: alguna de las tablas involucradas no existe en el esquema.");
                continue;
            }

            try {
                $count = (int) $connection->selectOne($check['sql'])->total;
                if ($count > 0) {
                    $this->fail("Huérfanos detectados en {$label}: {$count} registros sin referencia válida.");
                } else {
                    $this->info("  ✓ {$label}: 0 huérfanos.");
                }
            } catch (Throwable $e) {
                $this->fail("Error al evaluar relación {$label}: " . $e->getMessage());
            }
        }
    }

    private function reportAggregateMetrics(ConnectionInterface $connection, string $schema): void
    {
        $this->line('9. Conteos y métricas agregadas (sin información personal):');

        // Pagos
        $totalPagos = 0;
        $sumaPagos = 0.0;

        if ($this->tableExists($connection, $schema, 'pagos')) {
            $pagosStats = $connection->selectOne('SELECT COUNT(*) AS total, COALESCE(SUM(monto_pagado), 0) AS suma FROM pagos');
            $totalPagos = (int) ($pagosStats->total ?? 0);
            $sumaPagos = (float) ($pagosStats->suma ?? 0.0);
            $this->line(sprintf(
                '  · Pagos (tabla pagos): %s registros | Monto total: S/ %s',
                number_format($totalPagos),
                number_format($sumaPagos, 2, '.', ',')
            ));
        } elseif ($this->tableExists($connection, $schema, 'pagos_propia')) {
            $pagosStats = $connection->selectOne('SELECT COUNT(*) AS total, COALESCE(SUM(monto_pagado), 0) AS suma FROM pagos_propia');
            $totalPagos = (int) ($pagosStats->total ?? 0);
            $sumaPagos = (float) ($pagosStats->suma ?? 0.0);
            $this->line(sprintf(
                '  · Pagos (tabla histórica pagos_propia): %s registros | Monto total: S/ %s',
                number_format($totalPagos),
                number_format($sumaPagos, 2, '.', ',')
            ));
        } else {
            $this->line('  · Pagos: ninguna tabla de pagos encontrada.');
        }

        // Users
        $usersTotal = 0;
        if ($this->tableExists($connection, $schema, 'users')) {
            $usersTotal = (int) $connection->selectOne('SELECT COUNT(*) AS total FROM users')->total;
            $hasActive = (bool) $connection->selectOne(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [$schema, 'users', 'active']
            );

            $usersActive = $hasActive
                ? (int) $connection->selectOne('SELECT COUNT(*) AS total FROM users WHERE active = 1')->total
                : 0;
            $usersInactive = $hasActive
                ? (int) $connection->selectOne('SELECT COUNT(*) AS total FROM users WHERE active = 0')->total
                : 0;

            $rolesDistribution = collect($connection->select(
                'SELECT role, COUNT(*) AS total FROM users GROUP BY role ORDER BY role'
            ))->map(fn ($r) => "{$r->role}:{$r->total}")->implode(', ');

            $this->line(sprintf(
                '  · Usuarios: %d total (activos: %d, inactivos: %d) | Roles: %s',
                $usersTotal,
                $usersActive,
                $usersInactive,
                $rolesDistribution
            ));
        }

        // Tablas clave
        $tablesCounts = [
            'clientes_cuentas',
            'ccd_clientes',
            'asignar_clientes',
            'promesas_pago',
            'promesa_cuotas',
            'promesa_operaciones',
            'cna_solicitudes',
            'cliente_bloqueos',
            'pagos_lotes',
            'notifications',
        ];

        $counts = [];
        foreach ($tablesCounts as $table) {
            if ($this->tableExists($connection, $schema, $table)) {
                $cnt = (int) $connection->selectOne("SELECT COUNT(*) AS total FROM `{$table}`")->total;
                $counts[$table] = $cnt;
            }
        }

        $metricsList = collect($counts)->map(fn ($val, $tbl) => "{$tbl}: " . number_format($val))->implode(' | ');
        $this->line("  · Registros por tabla: {$metricsList}");

        // Comparación opcional contra línea base previa
        $baselineFile = (string) $this->option('baseline-file');
        if ($baselineFile !== '') {
            $this->compareAgainstBaselineFile($baselineFile, $totalPagos, $sumaPagos, $usersTotal, $counts);
        }
    }

    private function compareAgainstBaselineFile(
        string $filePath,
        int $actualPagos,
        float $actualSumaPagos,
        int $actualUsers,
        array $actualCounts
    ): void {
        $this->line("  · Comparando contra línea base pre-migración: {$filePath}");

        if (! file_exists($filePath)) {
            $this->reportWarning("Archivo de línea base '{$filePath}' no encontrado; se omitió la comparación.");
            return;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (! is_array($data)) {
            $this->reportWarning("El archivo '{$filePath}' no contiene un JSON válido.");
            return;
        }

        // Comparar pagos
        if (isset($data['pagos']['total'])) {
            $expected = (int) $data['pagos']['total'];
            if ($actualPagos < $expected) {
                $this->fail("Discrepancia en pagos: actual {$actualPagos} < esperado {$expected}.");
            } else {
                $this->info("  ✓ Validación línea base pagos: {$actualPagos} registros (esperado >= {$expected}).");
            }
        }

        if (isset($data['pagos']['suma'])) {
            $expectedSum = (float) $data['pagos']['suma'];
            if (round($actualSumaPagos, 2) < round($expectedSum, 2)) {
                $this->fail("Discrepancia en suma de pagos: actual S/ {$actualSumaPagos} < esperado S/ {$expectedSum}.");
            } else {
                $this->info("  ✓ Validación línea base suma pagos: S/ {$actualSumaPagos} (esperado >= S/ {$expectedSum}).");
            }
        }

        // Comparar usuarios
        if (isset($data['users']['total'])) {
            $expectedUsers = (int) $data['users']['total'];
            if ($actualUsers < $expectedUsers) {
                $this->fail("Discrepancia en usuarios: actual {$actualUsers} < esperado {$expectedUsers}.");
            } else {
                $this->info("  ✓ Validación línea base usuarios: {$actualUsers} registros (esperado >= {$expectedUsers}).");
            }
        }

        // Comparar tablas
        foreach ($data['counts'] ?? [] as $tbl => $expectedCount) {
            $actual = $actualCounts[$tbl] ?? null;
            if ($actual !== null && $actual < (int) $expectedCount) {
                $this->fail("Discrepancia en {$tbl}: actual {$actual} < esperado {$expectedCount}.");
            }
        }
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
