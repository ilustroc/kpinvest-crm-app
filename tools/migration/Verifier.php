<?php

declare(strict_types=1);

/**
 * Verificacion de seguridad e integridad de la copia enmascarada.
 *
 * Solo reporta nombres de tablas/columnas y conteos agregados. Nunca
 * devuelve valores originales ni valores enmascarados.
 */
final class Verifier
{
    private PDO $source;
    private PDO $target;
    private string $sourceDb;
    private string $targetDb;

    public function __construct(PDO $source, PDO $target, string $sourceDb, string $targetDb)
    {
        $this->source = $source;
        $this->target = $target;
        $this->sourceDb = $sourceDb;
        $this->targetDb = $targetDb;
    }

    /**
     * La referencia y la copia viven en servidores distintos. Cada consulta
     * debe usar la conexion del servidor al que pertenece la base.
     */
    private function pdoForDatabase(string $database): PDO
    {
        if ($database === $this->sourceDb) {
            return $this->source;
        }
        if ($database === $this->targetDb) {
            return $this->target;
        }

        throw new RuntimeException('Base no reconocida por el verificador: ' . $database);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $failures = [];
        $report = [
            'ok' => true,
            'failures' => [],
            'table_counts' => $this->tableCounts($failures),
            'foreign_key_orphans' => $this->foreignKeyOrphans($failures),
            'unique_violations' => $this->uniqueViolations($failures),
            'sums' => $this->sums($failures),
            'roles' => $this->roles($failures),
            'active_flags' => $this->activeFlags($failures),
            'lot_reconciliation' => $this->lotReconciliation($failures),
            'promise_cases' => $this->promiseCases($failures),
            'pii_exact_matches' => $this->piiExactMatches($failures),
            'distinct_counts' => $this->distinctCounts($failures),
            'synthetic_patterns' => $this->syntheticPatterns($failures),
            'json_valid' => $this->jsonValid($failures),
            'paths' => $this->paths($failures),
            'security' => $this->security($failures),
        ];

        $report['failures'] = array_values(array_unique($failures));
        $report['ok'] = $report['failures'] === [];

        return $report;
    }

    /**
     * @param list<string> $failures
     * @return array<string, array{source:int,masked:int,expected?:string}>
     */
    private function tableCounts(array &$failures): array
    {
        $out = [];
        foreach (expected_tables() as $table) {
            $source = row_count($this->source, $this->sourceDb, $table);
            $masked = row_count($this->target, $this->targetDb, $table);
            $out[$table] = ['source' => $source, 'masked' => $masked];

            if ($table === 'personal_access_tokens') {
                if ($masked !== 0) {
                    $failures[] = "personal_access_tokens debe quedar vacia; tiene {$masked} filas";
                }
                continue;
            }

            if ($source !== $masked) {
                $failures[] = "Conteo distinto en {$table}: origen {$source}, enmascarada {$masked}";
            }
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, int>
     */
    private function foreignKeyOrphans(array &$failures): array
    {
        $relations = [
            ['cliente_bloqueos', 'user_id', 'users', 'id'],
            ['pagos_propia', 'lote_id', 'pagos_lotes', 'id'],
            ['promesa_cuotas', 'promesa_id', 'promesas_pago', 'id'],
            ['promesa_operaciones', 'promesa_id', 'promesas_pago', 'id'],
            ['promesas_pago', 'aprobado_por', 'users', 'id'],
            ['promesas_pago', 'pre_aprobado_por', 'users', 'id'],
            ['promesas_pago', 'rechazado_por', 'users', 'id'],
            ['promesas_pago', 'user_id', 'users', 'id'],
        ];

        $out = [];
        foreach ($relations as [$child, $column, $parent, $parentColumn]) {
            $sql = 'SELECT COUNT(*) FROM ' . ident($this->targetDb) . '.' . ident($child) . ' c ' .
                'LEFT JOIN ' . ident($this->targetDb) . '.' . ident($parent) . ' p ' .
                'ON c.' . ident($column) . ' = p.' . ident($parentColumn) . ' ' .
                'WHERE c.' . ident($column) . ' IS NOT NULL AND p.' . ident($parentColumn) . ' IS NULL';
            $count = (int) $this->target->query($sql)->fetchColumn();
            $label = "{$child}.{$column} -> {$parent}.{$parentColumn}";
            $out[$label] = $count;
            if ($count !== 0) {
                $failures[] = "FK huerfana en {$label}: {$count}";
            }
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, int>
     */
    private function uniqueViolations(array &$failures): array
    {
        $checks = [
            ['users', ['email']],
            ['asignar_clientes', ['numdoc', 'operacion']],
            ['cliente_bloqueos', ['user_id', 'dni']],
            ['promesa_operaciones', ['promesa_id', 'operacion']],
            ['cna_solicitudes', ['nro_carta']],
            ['personal_access_tokens', ['token']],
        ];

        $out = [];
        foreach ($checks as [$table, $columns]) {
            $cols = implode(',', array_map('ident', $columns));
            $sql = 'SELECT COUNT(*) FROM (SELECT ' . $cols . ' FROM ' .
                ident($this->targetDb) . '.' . ident($table) .
                ' GROUP BY ' . $cols . ' HAVING COUNT(*) > 1) d';
            $count = (int) $this->target->query($sql)->fetchColumn();
            $label = $table . '(' . implode(',', $columns) . ')';
            $out[$label] = $count;
            if ($count !== 0) {
                $failures[] = "Duplicados en clave unica {$label}: {$count}";
            }
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, array{source:string,masked:string}>
     */
    private function sums(array &$failures): array
    {
        $checks = [
            ['clientes_cuentas', 'deuda_total'],
            ['pagos_propia', 'monto_pagado'],
            ['promesas_pago', 'monto'],
            ['promesa_cuotas', 'monto'],
            ['cna_solicitudes', 'monto_pagado'],
        ];

        $out = [];
        foreach ($checks as [$table, $column]) {
            $source = (string) $this->source->query(
                'SELECT COALESCE(SUM(' . ident($column) . '),0) FROM ' .
                ident($this->sourceDb) . '.' . ident($table)
            )->fetchColumn();
            $masked = (string) $this->target->query(
                'SELECT COALESCE(SUM(' . ident($column) . '),0) FROM ' .
                ident($this->targetDb) . '.' . ident($table)
            )->fetchColumn();
            $label = "{$table}.{$column}";
            $out[$label] = ['source' => $source, 'masked' => $masked];

            if (!self::decimalEquals($source, $masked)) {
                $failures[] = "Suma distinta en {$label}: origen {$source}, enmascarada {$masked}";
            }
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, array<string, int>>
     */
    private function roles(array &$failures): array
    {
        $source = $this->roleCounts($this->sourceDb);
        $masked = $this->roleCounts($this->targetDb);

        $allowed = MIGRATION_ALLOWED_ROLES;
        foreach (array_keys($source + $masked) as $role) {
            if (!in_array($role, $allowed, true)) {
                $failures[] = "Rol no permitido en la copia: {$role}";
            }
        }
        if ($source !== $masked) {
            $failures[] = 'Los conteos por rol no se preservaron';
        }

        return ['source' => $source, 'masked' => $masked];
    }

    /**
     * @return array<string, int>
     */
    private function roleCounts(string $database): array
    {
        $rows = $this->pdoForDatabase($database)->query(
            'SELECT role, COUNT(*) AS total FROM ' . ident($database) . '.users GROUP BY role ORDER BY role'
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['role']] = (int) $row['total'];
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, array{source:array<string,int>,masked:array<string,int>}>
     */
    private function activeFlags(array &$failures): array
    {
        $source = $this->flags($this->sourceDb);
        $masked = $this->flags($this->targetDb);
        if ($source !== $masked) {
            $failures[] = 'Los indicadores active/is_active no se preservaron';
        }

        return ['source' => $source, 'masked' => $masked];
    }

    /**
     * @return array<string, int>
     */
    private function flags(string $database): array
    {
        $row = $this->pdoForDatabase($database)->query(
            'SELECT COALESCE(SUM(active),0) AS active, ' .
            'COALESCE(SUM(is_active),0) AS is_active, ' .
            'COALESCE(SUM(active <> is_active),0) AS mismatch ' .
            'FROM ' . ident($database) . '.users'
        )->fetch();

        return [
            'active' => (int) $row['active'],
            'is_active' => (int) $row['is_active'],
            'mismatch' => (int) $row['mismatch'],
        ];
    }

    /**
     * @param list<string> $failures
     * @return array<string, array<string, int>>
     */
    private function lotReconciliation(array &$failures): array
    {
        $source = $this->lotAggregates($this->sourceDb);
        $masked = $this->lotAggregates($this->targetDb);
        if ($source !== $masked) {
            $failures[] = 'La conciliacion de lotes/pagos cambio en la copia enmascarada';
        }

        return ['source' => $source, 'masked' => $masked];
    }

    /**
     * @return array<string, int>
     */
    private function lotAggregates(string $database): array
    {
        $sql = 'SELECT COUNT(*) AS total, ' .
            'COALESCE(SUM(x.declarado = x.actual),0) AS exact, ' .
            'COALESCE(SUM(x.declarado <> x.actual),0) AS inconsistent, ' .
            'COALESCE(SUM(x.declarado > x.actual),0) AS overdeclared, ' .
            'COALESCE(SUM(x.declarado < x.actual),0) AS underdeclared, ' .
            'COALESCE(SUM(x.actual = 0),0) AS zero_payments, ' .
            'COALESCE(SUM(x.actual = 0 AND x.declarado > 0),0) AS zero_payments_declared, ' .
            'COALESCE(SUM(x.actual > 0 AND x.declarado > x.actual),0) AS over_with_payments, ' .
            'COALESCE(SUM(x.declarado),0) AS declared_sum, ' .
            '(SELECT COUNT(*) FROM ' . ident($database) . '.pagos_propia) AS payments ' .
            'FROM (SELECT l.id, l.total_registros AS declarado, COUNT(p.id) AS actual ' .
            'FROM ' . ident($database) . '.pagos_lotes l ' .
            'LEFT JOIN ' . ident($database) . '.pagos_propia p ON p.lote_id = l.id ' .
            'GROUP BY l.id, l.total_registros) x';
        $row = $this->pdoForDatabase($database)->query($sql)->fetch();
        $out = [];
        foreach ($row as $key => $value) {
            $out[(string) $key] = (int) $value;
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, array<string, int>>
     */
    private function promiseCases(array &$failures): array
    {
        $source = $this->promiseAggregates($this->sourceDb);
        $masked = $this->promiseAggregates($this->targetDb);
        if ($source !== $masked) {
            $failures[] = 'Los casos conocidos de promesas/pagos cambiaron en la copia enmascarada';
        }

        return ['source' => $source, 'masked' => $masked];
    }

    /**
     * @return array<string, int>
     */
    private function promiseAggregates(string $database): array
    {
        $pdo = $this->pdoForDatabase($database);
        $promises = $pdo->query(
            'SELECT COALESCE(SUM(monto = 0),0) AS zero_amount, ' .
            'COALESCE(SUM(fecha_promesa < \'2000-01-01\'),0) AS old_promise, ' .
            'COALESCE(SUM(fecha_pago < \'2000-01-01\'),0) AS old_payment ' .
            'FROM ' . ident($database) . '.promesas_pago'
        )->fetch();
        $payments = $pdo->query(
            'SELECT COALESCE(SUM(fecha < \'2000-01-01\'),0) AS old_payment ' .
            'FROM ' . ident($database) . '.pagos_propia'
        )->fetch();

        return [
            'zero_amount' => (int) $promises['zero_amount'],
            'old_promise' => (int) $promises['old_promise'],
            'old_payment' => (int) $promises['old_payment'],
            'payments_old' => (int) $payments['old_payment'],
        ];
    }

    /**
     * @param list<string> $failures
     * @return array<string, array{source_distinct:int,masked_distinct:int,exact_matches:int}>
     */
    private function piiExactMatches(array &$failures): array
    {
        $out = [];
        foreach ($this->piiColumns() as [$table, $column]) {
            $sourceSet = [];
            $sourceStmt = $this->source->query($this->distinctSql($this->sourceDb, $table, $column));
            while (($value = $sourceStmt->fetchColumn()) !== false) {
                if ($this->isNonPiiPlaceholder($table, $column, $value)) {
                    continue;
                }
                $sourceSet[hash('sha256', (string) $value)] = true;
            }

            $maskedSeen = [];
            $matches = 0;
            $maskedStmt = $this->target->query($this->distinctSql($this->targetDb, $table, $column));
            while (($value = $maskedStmt->fetchColumn()) !== false) {
                if ($this->isNonPiiPlaceholder($table, $column, $value)) {
                    continue;
                }
                $hash = hash('sha256', (string) $value);
                $maskedSeen[$hash] = true;
                if (isset($sourceSet[$hash])) {
                    $matches++;
                }
            }

            $label = "{$table}.{$column}";
            $out[$label] = [
                'source_distinct' => count($sourceSet),
                'masked_distinct' => count($maskedSeen),
                'exact_matches' => $matches,
            ];

            if ($matches !== 0) {
                $failures[] = "PII original detectada en {$label}: {$matches} valores";
            }
        }

        return $out;
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    private function piiColumns(): array
    {
        return [
            ['users', 'name'],
            ['users', 'email'],
            ['users', 'password'],
            ['users', 'remember_token'],
            ['clientes_cuentas', 'numdoc'],
            ['clientes_cuentas', 'cuenta'],
            ['clientes_cuentas', 'nombre'],
            ['clientes_cuentas', 'dpto'],
            ['clientes_cuentas', 'operacion'],
            ['clientes_cuentas', 'direccion'],
            ['clientes_cuentas', 'provincia'],
            ['clientes_cuentas', 'distrito'],
            ['cliente_bloqueos', 'dni'],
            ['cliente_bloqueos', 'motivo'],
            ['pagos_lotes', 'archivo'],
            ['pagos_propia', 'dni'],
            ['pagos_propia', 'operacion'],
            ['pagos_propia', 'nombre_cliente'],
            ['pagos_propia', 'gestor'],
            ['pagos_propia', 'cuenta_recaudo'],
            ['promesas_pago', 'dni'],
            ['promesas_pago', 'telefono'],
            ['promesas_pago', 'operacion'],
            ['promesas_pago', 'nota'],
            ['promesas_pago', 'nota_preaprobacion'],
            ['promesas_pago', 'nota_aprobacion'],
            ['promesas_pago', 'nota_rechazo'],
            ['promesa_operaciones', 'operacion'],
            ['cna_solicitudes', 'dni'],
            ['cna_solicitudes', 'titular'],
            ['cna_solicitudes', 'operaciones'],
            ['cna_solicitudes', 'observacion'],
            ['cna_solicitudes', 'nota'],
            ['cna_solicitudes', 'motivo_rechazo'],
            ['cna_solicitudes', 'docx_path'],
            ['cna_solicitudes', 'pdf_path'],
            ['asignar_clientes', 'numdoc'],
            ['asignar_clientes', 'operacion'],
            ['asignar_clientes', 'name'],
            ['ccd_clientes', 'numdoc'],
            ['ccd_clientes', 'pdf'],
            ['ccd_clientes', 'link'],
            ['notifications', 'data'],
        ];
    }

    private function distinctSql(string $database, string $table, string $column): string
    {
        return 'SELECT DISTINCT ' . ident($column) . ' FROM ' .
            ident($database) . '.' . ident($table) . ' WHERE ' . ident($column) .
            ' IS NOT NULL AND ' . ident($column) . ' <> \'\'';
    }

    private function isNonPiiPlaceholder(string $table, string $column, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return true;
        }

        if ($table === 'cna_solicitudes' && $column === 'operaciones') {
            return $text === '[]' || $text === 'null';
        }

        return false;
    }

    /**
     * @param list<string> $failures
     * @return array<string, array{source:int,masked:int,delta:int}>
     */
    private function distinctCounts(array &$failures): array
    {
        // La mascara normaliza los dominios numericos (quita separadores) y
        // conserva el valor recortado cuando no hay digitos. El conteo debe
        // usar la misma clave; de lo contrario, dos formatos del mismo DNI,
        // operacion o telefono parecen una perdida de distintos. Los valores
        // de cuenta sin digitos se comparan de forma binaria para no colapsar
        // variantes de mayusculas/minusculas de la colacion de origen.
        $columns = [
            ['users', 'email', 'binarytrim'],
            ['clientes_cuentas', 'numdoc', 'maskkey'],
            ['clientes_cuentas', 'cuenta', 'maskkey'],
            ['clientes_cuentas', 'operacion', 'maskkey'],
            ['cliente_bloqueos', 'dni', 'maskkey'],
            ['pagos_propia', 'dni', 'maskkey'],
            ['pagos_propia', 'operacion', 'maskkey'],
            ['pagos_propia', 'cuenta_recaudo', 'maskkey'],
            ['promesas_pago', 'dni', 'maskkey'],
            ['promesas_pago', 'telefono', 'maskkey'],
            ['promesas_pago', 'operacion', 'maskkey'],
            ['promesa_operaciones', 'operacion', 'maskkey'],
            ['cna_solicitudes', 'dni', 'maskkey'],
            ['cna_solicitudes', 'operaciones', 'raw'],
            ['asignar_clientes', 'numdoc', 'maskkey'],
            ['asignar_clientes', 'operacion', 'maskkey'],
            ['ccd_clientes', 'numdoc', 'maskkey'],
        ];

        $out = [];
        foreach ($columns as [$table, $column, $mode]) {
            $source = $this->countDistinct($this->sourceDb, $table, $column, $mode);
            $masked = $this->countDistinct($this->targetDb, $table, $column, $mode);
            $label = "{$table}.{$column}";
            $out[$label] = [
                'source' => $source,
                'masked' => $masked,
                'delta' => $masked - $source,
                'method' => $mode,
            ];
            if ($source !== $masked) {
                $failures[] = "Distintos cambiados en {$label}: origen {$source}, enmascarada {$masked}";
            }
        }

        return $out;
    }

    private function countDistinct(string $database, string $table, string $column, string $mode = 'raw'): int
    {
        $columnSql = ident($column);
        if ($mode === 'maskkey') {
            $digits = 'REGEXP_REPLACE(' . $columnSql . ", '[^0-9]', '')";
            $expression = 'CAST(CASE WHEN ' . $digits . ' <> \'\' THEN ' . $digits .
                ' ELSE TRIM(' . $columnSql . ') END AS BINARY)';
        }
        elseif ($mode === 'binarytrim') {
            $expression = 'CAST(TRIM(' . $columnSql . ') AS BINARY)';
        }
        else {
            $expression = $columnSql;
        }

        return (int) $this->pdoForDatabase($database)->query(
            'SELECT COUNT(DISTINCT ' . $expression . ') FROM ' .
            ident($database) . '.' . ident($table) . ' WHERE ' . $columnSql . ' IS NOT NULL'
        )->fetchColumn();
    }

    /**
     * @param list<string> $failures
     * @return array<string, int>
     */
    private function syntheticPatterns(array &$failures): array
    {
        $patterns = [
            ['users', 'name', '^USUARIO_[0-9]+$'],
            ['users', 'email', '^usuario[0-9]+@example[.]invalid$'],
            ['clientes_cuentas', 'nombre', '^(CLIENTE_[0-9]{6}|CLIENTE_000000)$'],
            ['clientes_cuentas', 'direccion', '^DIRECCION_[0-9]{6}$'],
            ['clientes_cuentas', 'dpto', '^DPTO_[0-9]{2,}$'],
            ['clientes_cuentas', 'provincia', '^PROV_[0-9]{2,}$'],
            ['clientes_cuentas', 'distrito', '^DIST_[0-9]{3,}$'],
            ['cliente_bloqueos', 'motivo', '^MOTIVO_[0-9]{6}$'],
            ['pagos_lotes', 'archivo', '^lote_[0-9]{6}[.][A-Za-z0-9]+$'],
            ['pagos_propia', 'nombre_cliente', '^(CLIENTE_[0-9]{6}|CLIENTE_000000)$'],
            ['pagos_propia', 'gestor', '^GESTOR_[0-9]{6}$'],
            ['promesas_pago', 'nota', '^TEXTO_[0-9]{6}$'],
            ['promesas_pago', 'nota_preaprobacion', '^TEXTO_[0-9]{6}$'],
            ['promesas_pago', 'nota_aprobacion', '^TEXTO_[0-9]{6}$'],
            ['promesas_pago', 'nota_rechazo', '^TEXTO_[0-9]{6}$'],
            ['cna_solicitudes', 'titular', '^(CLIENTE_[0-9]{6}|CLIENTE_000000)$'],
            ['cna_solicitudes', 'observacion', '^TEXTO_[0-9]{6}$'],
            ['cna_solicitudes', 'nota', '^TEXTO_[0-9]{6}$'],
            ['cna_solicitudes', 'motivo_rechazo', '^TEXTO_[0-9]{6}$'],
            ['cna_solicitudes', 'docx_path', '^cna/docx/CLIENTE_[0-9]{6}/CNA_[0-9]{6}[.]docx$'],
            ['cna_solicitudes', 'pdf_path', '^cna/pdf/CLIENTE_[0-9]{6}/CNA_[0-9]{6}[.]pdf$'],
            ['asignar_clientes', 'name', '^ASESOR_[0-9]{6}$'],
            ['ccd_clientes', 'pdf', '^CCD_[0-9]{6}[.]pdf$'],
            ['ccd_clientes', 'link', '^ccd/CCD_[0-9]{6}/documento[.]pdf$'],
        ];

        $out = [];
        foreach ($patterns as [$table, $column, $regex]) {
            $sql = 'SELECT COUNT(*) FROM ' . ident($this->targetDb) . '.' . ident($table) .
                ' WHERE ' . ident($column) . ' IS NOT NULL AND ' . ident($column) .
                ' <> \'\' AND ' . ident($column) . ' NOT REGEXP ?';
            $stmt = $this->target->prepare($sql);
            $stmt->execute([$regex]);
            $bad = (int) $stmt->fetchColumn();
            $label = "{$table}.{$column}";
            $out[$label] = $bad;
            if ($bad !== 0) {
                $failures[] = "Formato sintetico inesperado en {$label}: {$bad} filas";
            }
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, int>
     */
    private function jsonValid(array &$failures): array
    {
        $checks = [
            'cna_solicitudes.operaciones' => 'SELECT COUNT(*) FROM ' .
                ident($this->targetDb) . '.cna_solicitudes WHERE JSON_VALID(operaciones) = 0',
            'notifications.data' => 'SELECT COUNT(*) FROM ' .
                ident($this->targetDb) . '.notifications WHERE JSON_VALID(data) = 0',
        ];

        $out = [];
        foreach ($checks as $label => $sql) {
            $count = (int) $this->target->query($sql)->fetchColumn();
            $out[$label] = $count;
            if ($count !== 0) {
                $failures[] = "JSON invalido en {$label}: {$count}";
            }
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, int>
     */
    private function paths(array &$failures): array
    {
        $columns = [
            ['pagos_lotes', 'archivo'],
            ['cna_solicitudes', 'docx_path'],
            ['cna_solicitudes', 'pdf_path'],
            ['ccd_clientes', 'pdf'],
            ['ccd_clientes', 'link'],
        ];

        $out = [];
        foreach ($columns as [$table, $column]) {
            $sql = 'SELECT COUNT(*) FROM ' . ident($this->targetDb) . '.' . ident($table) .
                ' WHERE ' . ident($column) . ' IS NOT NULL AND ' . ident($column) . ' <> \'\' AND (' .
                ident($column) . ' LIKE \'/%\' OR ' . ident($column) . ' LIKE \'%:%\' OR ' .
                ident($column) . ' LIKE \'%..%\' OR ' . ident($column) .
                ' LIKE CONCAT(CHAR(92), CHAR(92), \'%\'))';
            $count = (int) $this->target->query($sql)->fetchColumn();
            $label = "{$table}.{$column}";
            $out[$label] = $count;
            if ($count !== 0) {
                $failures[] = "Ruta absoluta o sospechosa en {$label}: {$count}";
            }
        }

        return $out;
    }

    /**
     * @param list<string> $failures
     * @return array<string, int>
     */
    private function security(array &$failures): array
    {
        $pat = (int) $this->target->query(
            'SELECT COUNT(*) FROM ' . ident($this->targetDb) . '.personal_access_tokens'
        )->fetchColumn();

        $badPasswords = (int) $this->target->query(
            'SELECT COUNT(*) FROM ' . ident($this->targetDb) . '.users ' .
            'WHERE password NOT LIKE \'$2y$%\' AND password NOT LIKE \'$argon2%\''
        )->fetchColumn();

        $out = [
            'personal_access_tokens_masked' => $pat,
            'password_format_bad' => $badPasswords,
            'test_users_expected' => 4,
        ];

        if ($pat !== 0) {
            $failures[] = "Tokens personales no eliminados: {$pat}";
        }
        if ($badPasswords !== 0) {
            $failures[] = "Hashes de contrasena no sinteticos: {$badPasswords}";
        }

        return $out;
    }

    private static function decimalEquals(string $left, string $right): bool
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, 2) === 0;
        }

        return abs((float) $left - (float) $right) < 0.01;
    }
}
