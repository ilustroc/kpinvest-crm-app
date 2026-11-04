<?php

declare(strict_types=1);

/**
 * ETL de enmascaramiento determinista V1 -> copia staging.
 *
 * - Conserva IDs, relaciones, importes, fechas, estados y cantidades.
 * - Reemplaza identificadores personales y credenciales.
 * - Mantiene consistencia entre tablas mediante mapas en memoria.
 * - No escribe mapas ni valores originales en disco.
 */
final class Masker
{
    private PDO $source;
    private PDO $target;
    private string $sourceDb;
    private string $targetDb;
    private string $key;
    private string $testHash;
    private string $randomHash;

    /** @var array<string, true> */
    private array $testUserIds = [];

    /** @var array<string, array<string, true>> */
    private array $sourceHashes = [
        'person' => [],
        'operation' => [],
        'account' => [],
        'phone' => [],
    ];

    /** @var array<string, array<string, string>> */
    private array $tokenCache = [
        'person' => [],
        'operation' => [],
        'account' => [],
        'phone' => [],
    ];

    /** @var array<string, array<string, true>> */
    private array $used = [
        'person' => [],
        'operation' => [],
        'account' => [],
        'phone' => [],
    ];

    /** @var array<string, int> */
    private array $personSeq = [];
    private int $personCounter = 0;

    /** @var array<string, array<string, int>> */
    private array $labelSeq = [];
    /** @var array<string, int> */
    private array $labelCounter = [];

    /** @var array<string, array<string, int>> */
    private array $geoSeq = [];
    /** @var array<string, int> */
    private array $geoCounter = [];

    public function __construct(
        PDO $source,
        PDO $target,
        string $sourceDb,
        string $targetDb,
        string $key,
        string $testPassword
    ) {
        $this->source = $source;
        $this->target = $target;
        $this->sourceDb = $sourceDb;
        $this->targetDb = $targetDb;
        $this->key = $key;
        // Hash determinista para que una reejecucion sobre datos sinteticos
        // produzca exactamente la misma copia. El salt no es secreto.
        $this->testHash = $this->bcryptDeterministic($testPassword, 'KpCrmG15TestSalt000000');
        $this->randomHash = $this->bcryptDeterministic(
            'synthetic-disabled-account',
            'KpCrmG15RandSalt000000'
        );
        $this->loadTestUsers();
    }

    private function bcryptDeterministic(string $password, string $salt): string
    {
        if (strlen($salt) !== 22) {
            throw new RuntimeException('Salt bcrypt sintetico invalido');
        }

        $hash = crypt($password, '$2y$10$' . $salt);
        if (!is_string($hash) || strlen($hash) !== 60) {
            throw new RuntimeException('No se pudo generar un hash bcrypt determinista');
        }

        return $hash;
    }

    /**
     * @return array<string, mixed>
     */
    public function maskAll(): array
    {
        if (database_table_count($this->target, $this->targetDb) !== 0) {
            throw new RuntimeException(
                'La base enmascarada debe estar vacia. Si quedo a medias, elimine solo esa base de staging y recreela.'
            );
        }

        $this->loadSourceHashes();
        $this->createSchema();

        $order = [
            'users',
            'clientes_cuentas',
            'cliente_bloqueos',
            'pagos_lotes',
            'pagos_propia',
            'promesas_pago',
            'promesa_cuotas',
            'promesa_operaciones',
            'cna_solicitudes',
            'asignar_clientes',
            'ccd_clientes',
            'notifications',
            'migrations',
        ];

        $counts = [];
        foreach ($order as $table) {
            $counts[$table] = $this->copyTable($table);
        }

        // Los tokens personales nunca se copian; la tabla queda vacia.
        $counts['personal_access_tokens'] = 0;

        return [
            'tables' => $counts,
            'total_rows' => array_sum($counts),
            'source_domains' => [
                'person_values' => count($this->sourceHashes['person']),
                'operation_values' => count($this->sourceHashes['operation']),
                'account_values' => count($this->sourceHashes['account']),
                'phone_values' => count($this->sourceHashes['phone']),
            ],
            'test_users' => count($this->testUserIds),
        ];
    }

    private function loadTestUsers(): void
    {
        $roles = assert_allowed_roles($this->source, $this->sourceDb);
        foreach (MIGRATION_ALLOWED_ROLES as $role) {
            if (!isset($roles[$role])) {
                throw new RuntimeException("No hay usuario con rol {$role}; detenga y revise la copia");
            }

            $stmt = $this->source->prepare(
                'SELECT id FROM ' . ident($this->sourceDb) . '.users WHERE role = ? ORDER BY id LIMIT 1'
            );
            $stmt->execute([$role]);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                throw new RuntimeException("No se pudo seleccionar el usuario de prueba para el rol {$role}");
            }
            $this->testUserIds[(string) $id] = true;
        }
    }

    private function loadSourceHashes(): void
    {
        $personColumns = [
            ['clientes_cuentas', 'numdoc'],
            ['cliente_bloqueos', 'dni'],
            ['pagos_propia', 'dni'],
            ['promesas_pago', 'dni'],
            ['cna_solicitudes', 'dni'],
            ['asignar_clientes', 'numdoc'],
            ['ccd_clientes', 'numdoc'],
        ];
        foreach ($personColumns as [$table, $column]) {
            $this->addDistinctHashes($table, $column, 'person');
        }

        $operationColumns = [
            ['clientes_cuentas', 'operacion'],
            ['pagos_propia', 'operacion'],
            ['promesas_pago', 'operacion'],
            ['promesa_operaciones', 'operacion'],
            ['asignar_clientes', 'operacion'],
        ];
        foreach ($operationColumns as [$table, $column]) {
            $this->addDistinctHashes($table, $column, 'operation');
        }

        $accountColumns = [
            ['clientes_cuentas', 'cuenta'],
            ['pagos_propia', 'cuenta_recaudo'],
        ];
        foreach ($accountColumns as [$table, $column]) {
            $this->addDistinctHashes($table, $column, 'account');
        }

        $this->addDistinctHashes('promesas_pago', 'telefono', 'phone');

        // Operaciones embebidas en el JSON de CNA.
        $stmt = $this->source->query(
            'SELECT operaciones FROM ' . ident($this->sourceDb) . '.cna_solicitudes
             WHERE operaciones IS NOT NULL AND operaciones <> \'\''
        );
        while (($json = $stmt->fetchColumn()) !== false) {
            $decoded = json_decode((string) $json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            foreach ($this->collectScalars($decoded) as $value) {
                $normalized = normalize_digits($value);
                if ($normalized !== '') {
                    $this->sourceHashes['operation'][hmac_hex('operation', $normalized, $this->key)] = true;
                }
            }
        }
    }

    private function addDistinctHashes(string $table, string $column, string $domain): void
    {
        $sql = 'SELECT DISTINCT ' . ident($column) .
            ' FROM ' . ident($this->sourceDb) . '.' . ident($table) .
            ' WHERE ' . ident($column) . ' IS NOT NULL AND ' . ident($column) . ' <> \'\'';
        $stmt = $this->source->query($sql);
        while (($value = $stmt->fetchColumn()) !== false) {
            $normalized = normalize_digits((string) $value);
            if ($normalized === '') {
                continue;
            }
            $this->sourceHashes[$domain][hmac_hex($domain, $normalized, $this->key)] = true;
        }
    }

    /**
     * @return list<string>
     */
    private function collectScalars(mixed $value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                array_push($out, ...$this->collectScalars($item));
            }
            return $out;
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            $text = (string) $value;
            return $text === '' ? [] : [$text];
        }

        return [];
    }

    private function createSchema(): void
    {
        $this->target->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (expected_tables() as $table) {
                $stmt = $this->source->query(
                    'SHOW CREATE TABLE ' . ident($this->sourceDb) . '.' . ident($table)
                );
                $row = $stmt->fetch();
                if (!is_array($row) || !isset($row['Create Table'])) {
                    throw new RuntimeException("No se pudo obtener el esquema de {$table}");
                }
                $this->target->exec((string) $row['Create Table']);
            }
        } finally {
            $this->target->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function copyTable(string $table): int
    {
        $columns = table_columns($this->source, $this->sourceDb, $table);
        $total = row_count($this->source, $this->sourceDb, $table);
        if ($total === 0) {
            return 0;
        }

        $last = null;
        $copied = 0;
        while (true) {
            $sql = 'SELECT * FROM ' . ident($this->sourceDb) . '.' . ident($table);
            if ($last !== null) {
                $sql .= ' WHERE id > :last';
            }
            $sql .= ' ORDER BY id LIMIT 500';

            $stmt = $this->source->prepare($sql);
            if ($last !== null) {
                $stmt->bindValue(':last', (string) $last, PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll();
            if ($rows === []) {
                break;
            }

            $batch = [];
            foreach ($rows as $row) {
                $batch[] = $this->transformRow($table, $row);
                $last = $row['id'];
            }

            $this->insertBatch($table, $columns, $batch);
            $copied += count($batch);
            if (count($batch) < 500) {
                break;
            }
        }

        if ($copied !== $total) {
            throw new RuntimeException("Conteo incompleto en {$table}: {$copied} de {$total}");
        }

        return $copied;
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $rows
     */
    private function insertBatch(string $table, array $columns, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columnSql = implode(',', array_map('ident', $columns));
        $tuple = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $sql = 'INSERT INTO ' . ident($table) . ' (' . $columnSql . ') VALUES ' .
            implode(',', array_fill(0, count($rows), $tuple));

        $stmt = $this->target->prepare($sql);
        $index = 1;
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $stmt->bindValue($index++, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
        }

        $this->target->beginTransaction();
        try {
            $stmt->execute();
            $this->target->commit();
        } catch (Throwable $e) {
            if ($this->target->inTransaction()) {
                $this->target->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function transformRow(string $table, array $row): array
    {
        $out = $row;
        $id = (int) ($row['id'] ?? 0);

        switch ($table) {
            case 'users':
                $out['name'] = sprintf('USUARIO_%03d', $id);
                $out['email'] = sprintf('usuario%d@example.invalid', $id);
                $out['password'] = isset($this->testUserIds[(string) $id])
                    ? $this->testHash
                    : $this->randomHash;
                $out['remember_token'] = null;
                break;

            case 'clientes_cuentas':
                $dni = $this->nullableString($row['numdoc'] ?? null);
                $out['numdoc'] = $this->maskPerson($dni);
                $out['cuenta'] = $this->maskAccount($this->nullableString($row['cuenta'] ?? null));
                $out['nombre'] = $this->personName($dni);
                $out['dpto'] = $this->geo($this->nullableString($row['dpto'] ?? null), 'DPTO');
                $out['operacion'] = $this->maskOperation($this->nullableString($row['operacion'] ?? null));
                $out['direccion'] = $this->text(
                    $this->nullableString($row['direccion'] ?? null),
                    'DIRECCION',
                    $id
                );
                $out['provincia'] = $this->geo($this->nullableString($row['provincia'] ?? null), 'PROV');
                $out['distrito'] = $this->geo($this->nullableString($row['distrito'] ?? null), 'DIST');
                break;

            case 'cliente_bloqueos':
                $out['dni'] = $this->maskPerson($this->nullableString($row['dni'] ?? null));
                $out['motivo'] = $this->text(
                    $this->nullableString($row['motivo'] ?? null),
                    'MOTIVO',
                    $id
                );
                break;

            case 'pagos_lotes':
                $out['archivo'] = $this->lotePath(
                    $this->nullableString($row['archivo'] ?? null),
                    $id
                );
                break;

            case 'pagos_propia':
                $dni = $this->nullableString($row['dni'] ?? null);
                $out['dni'] = $this->maskPerson($dni);
                $out['operacion'] = $this->maskOperation($this->nullableString($row['operacion'] ?? null));
                $out['nombre_cliente'] = $this->personName($dni);
                $out['gestor'] = $this->label($this->nullableString($row['gestor'] ?? null), 'GESTOR');
                $out['cuenta_recaudo'] = $this->maskAccount(
                    $this->nullableString($row['cuenta_recaudo'] ?? null)
                );
                break;

            case 'promesas_pago':
                $out['dni'] = $this->maskPerson($this->nullableString($row['dni'] ?? null));
                $out['telefono'] = $this->maskPhone($this->nullableString($row['telefono'] ?? null));
                $out['operacion'] = $this->maskOperation($this->nullableString($row['operacion'] ?? null));
                $out['nota'] = $this->text($this->nullableString($row['nota'] ?? null), 'TEXTO', $id);
                $out['nota_preaprobacion'] = $this->text(
                    $this->nullableString($row['nota_preaprobacion'] ?? null),
                    'TEXTO',
                    $id
                );
                $out['nota_aprobacion'] = $this->text(
                    $this->nullableString($row['nota_aprobacion'] ?? null),
                    'TEXTO',
                    $id
                );
                $out['nota_rechazo'] = $this->text(
                    $this->nullableString($row['nota_rechazo'] ?? null),
                    'TEXTO',
                    $id
                );
                break;

            case 'promesa_operaciones':
                $out['operacion'] = $this->maskOperation(
                    $this->nullableString($row['operacion'] ?? null)
                );
                break;

            case 'cna_solicitudes':
                $dni = $this->nullableString($row['dni'] ?? null);
                $out['dni'] = $this->maskPerson($dni);
                $out['titular'] = $this->personName($dni);
                $out['operaciones'] = $this->maskCnaOperations(
                    $this->nullableString($row['operaciones'] ?? null),
                    $id
                );
                $out['observacion'] = $this->text(
                    $this->nullableString($row['observacion'] ?? null),
                    'TEXTO',
                    $id
                );
                $out['nota'] = $this->text($this->nullableString($row['nota'] ?? null), 'TEXTO', $id);
                $out['motivo_rechazo'] = $this->text(
                    $this->nullableString($row['motivo_rechazo'] ?? null),
                    'TEXTO',
                    $id
                );
                $out['docx_path'] = $this->cnaPath(
                    $this->nullableString($row['docx_path'] ?? null),
                    $dni,
                    $id,
                    'docx'
                );
                $out['pdf_path'] = $this->cnaPath(
                    $this->nullableString($row['pdf_path'] ?? null),
                    $dni,
                    $id,
                    'pdf'
                );
                break;

            case 'asignar_clientes':
                $out['numdoc'] = $this->maskPerson($this->nullableString($row['numdoc'] ?? null));
                $out['operacion'] = $this->maskOperation(
                    $this->nullableString($row['operacion'] ?? null)
                );
                $out['name'] = $this->label($this->nullableString($row['name'] ?? null), 'ASESOR');
                break;

            case 'ccd_clientes':
                $out['numdoc'] = $this->maskPerson($this->nullableString($row['numdoc'] ?? null));
                $pdf = $this->nullableString($row['pdf'] ?? null);
                $link = $this->nullableString($row['link'] ?? null);
                $out['pdf'] = ($pdf === null || trim($pdf) === '')
                    ? $pdf
                    : sprintf('CCD_%06d.pdf', $id);
                $out['link'] = ($link === null || trim($link) === '')
                    ? $link
                    : sprintf('ccd/CCD_%06d/documento.pdf', $id);
                break;

            case 'notifications':
                $out['data'] = $this->notificationData($this->nullableString($row['data'] ?? null));
                break;

            case 'promesa_cuotas':
            case 'migrations':
                break;

            default:
                throw new RuntimeException("Tabla sin regla de enmascaramiento: {$table}");
        }

        return $out;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function maskPerson(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (trim($value) === '') {
            return $value;
        }

        $normalized = normalize_digits($value);
        $hash = hmac_hex('person', $normalized, $this->key);
        if (!isset($this->personSeq[$hash])) {
            $this->personSeq[$hash] = ++$this->personCounter;
        }

        return $this->numeric($normalized, 'person', $this->sourceHashes['person']);
    }

    private function personName(?string $dni): string
    {
        if ($dni === null || trim($dni) === '') {
            return 'CLIENTE_000000';
        }

        $this->maskPerson($dni);
        $hash = hmac_hex('person', normalize_digits($dni), $this->key);
        $seq = $this->personSeq[$hash] ?? 0;

        return sprintf('CLIENTE_%06d', $seq);
    }

    private function maskOperation(?string $value): ?string
    {
        return $this->maskNumericDomain($value, 'operation', $this->sourceHashes['operation']);
    }

    private function maskAccount(?string $value): ?string
    {
        return $this->maskNumericDomain($value, 'account', $this->sourceHashes['account']);
    }

    private function maskPhone(?string $value): ?string
    {
        return $this->maskNumericDomain($value, 'phone', $this->sourceHashes['phone']);
    }

    private function maskNumericDomain(?string $value, string $domain, array $sourceHashes): ?string
    {
        if ($value === null) {
            return null;
        }
        if (trim($value) === '') {
            return $value;
        }

        return $this->numeric(normalize_digits($value), $domain, $sourceHashes);
    }

    /**
     * @param array<string, true> $sourceHashes
     */
    private function numeric(string $normalized, string $domain, array $sourceHashes): string
    {
        if ($normalized === '') {
            return '';
        }

        $hash = hmac_hex($domain, $normalized, $this->key);
        if (isset($this->tokenCache[$domain][$hash])) {
            return $this->tokenCache[$domain][$hash];
        }

        $isDigits = ctype_digit($normalized);
        for ($counter = 0; $counter < 10000; $counter++) {
            $token = $isDigits
                ? $this->makeDigits($normalized, $domain, $counter)
                : $this->makeAlpha($normalized, $domain, $counter);

            $tokenHash = hmac_hex($domain, $token, $this->key);
            if (isset($sourceHashes[$tokenHash])) {
                continue;
            }
            if (isset($this->used[$domain][$token])) {
                continue;
            }

            $this->used[$domain][$token] = true;
            $this->tokenCache[$domain][$hash] = $token;

            return $token;
        }

        throw new RuntimeException("No se pudo generar un token unico para el dominio {$domain}");
    }

    private function makeDigits(string $source, string $domain, int $counter): string
    {
        $length = strlen($source);
        if ($length === 0) {
            return '';
        }

        $out = '';
        $round = 0;
        $firstByte = 0;
        while (strlen($out) < $length) {
            $material = hash_hmac(
                'sha256',
                $domain . "\0" . $source . "\0" . $counter . "\0" . $round,
                $this->key,
                true
            );
            if ($round === 0) {
                $firstByte = ord($material[0]);
            }
            $materialLength = strlen($material);
            for ($i = 0; $i < $materialLength && strlen($out) < $length; $i++) {
                $out .= (string) (ord($material[$i]) % 10);
            }
            $round++;
        }

        if ($source[0] !== '0' && $out[0] === '0') {
            $out[0] = (string) (1 + ($firstByte % 9));
        }

        return $out;
    }

    private function makeAlpha(string $source, string $domain, int $counter): string
    {
        $hex = strtoupper(hash_hmac(
            'sha256',
            $domain . "\0" . $source . "\0" . $counter,
            $this->key
        ));
        $length = strlen($source);

        return substr($hex, 0, $length);
    }

    private function text(?string $value, string $prefix, int $id): ?string
    {
        if ($value === null) {
            return null;
        }
        if (trim($value) === '') {
            return $value;
        }

        return sprintf('%s_%06d', $prefix, $id);
    }

    private function geo(?string $value, string $type): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        $hash = hmac_hex('geo:' . $type, trim($value), $this->key);
        if (!isset($this->geoSeq[$type][$hash])) {
            $this->geoCounter[$type] = ($this->geoCounter[$type] ?? 0) + 1;
            $this->geoSeq[$type][$hash] = $this->geoCounter[$type];
        }

        $width = $type === 'DIST' ? 3 : 2;

        return sprintf('%s_%0' . $width . 'd', $type, $this->geoSeq[$type][$hash]);
    }

    private function label(?string $value, string $prefix): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        $hash = hmac_hex('label:' . $prefix, trim($value), $this->key);
        if (!isset($this->labelSeq[$prefix][$hash])) {
            $this->labelCounter[$prefix] = ($this->labelCounter[$prefix] ?? 0) + 1;
            $this->labelSeq[$prefix][$hash] = $this->labelCounter[$prefix];
        }

        return sprintf('%s_%06d', $prefix, $this->labelSeq[$prefix][$hash]);
    }

    private function lotePath(?string $value, int $id): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        $extension = pathinfo($value, PATHINFO_EXTENSION);
        $extension = preg_replace('/[^A-Za-z0-9]/', '', $extension);
        $extension = $extension === null || $extension === '' ? '.csv' : '.' . $extension;

        return sprintf('lote_%06d%s', $id, $extension);
    }

    private function cnaPath(?string $value, ?string $dni, int $id, string $extension): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        return sprintf(
            'cna/%s/%s/CNA_%06d.%s',
            $extension,
            $this->personName($dni),
            $id,
            $extension
        );
    }

    private function maskCnaOperations(?string $json, int $id): ?string
    {
        if ($json === null) {
            return null;
        }
        if (trim($json) === '') {
            return $json;
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("JSON invalido en cna_solicitudes.operaciones id={$id}; detenga la migracion");
        }

        $masked = $this->mapCnaOperations($decoded);
        $encoded = json_encode($masked, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException("No se pudo serializar cna_solicitudes.operaciones id={$id}");
        }

        return $encoded;
    }

    private function mapCnaOperations(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->mapCnaOperations($item);
            }
            return $out;
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            return $this->maskOperation((string) $value);
        }

        return $value;
    }

    private function notificationData(?string $json): string
    {
        if ($json === null || trim($json) === '') {
            return '{"mensaje":"Notificacion sintetica de staging"}';
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '{"mensaje":"Notificacion sintetica de staging"}';
        }

        $masked = $this->sanitizeNotification($decoded);
        $encoded = json_encode($masked, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('No se pudo serializar notifications.data');
        }

        return $encoded;
    }

    private function sanitizeNotification(mixed $value, string $key = ''): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $childKey => $item) {
                $out[$childKey] = $this->sanitizeNotification($item, (string) $childKey);
            }
            return $out;
        }

        $lower = strtolower($key);

        if (is_int($value) || is_float($value)) {
            $text = (string) $value;
            if (str_contains($lower, 'dni') || str_contains($lower, 'documento')) {
                return $this->maskPerson($text) ?? '00000000';
            }
            if (str_contains($lower, 'telefono') || str_contains($lower, 'phone') || str_contains($lower, 'celular')) {
                return $this->maskPhone($text) ?? '999999999';
            }
            if (str_contains($lower, 'operacion')) {
                return $this->maskOperation($text) ?? '000000';
            }
            if (str_contains($lower, 'cuenta')) {
                return $this->maskAccount($text) ?? '000000';
            }

            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }
        if (str_contains($lower, 'dni') || str_contains($lower, 'documento')) {
            return $this->maskPerson($value) ?? 'CLIENTE_000000';
        }
        if (str_contains($lower, 'email') || str_contains($lower, 'correo')) {
            return 'notificacion@example.invalid';
        }
        if (str_contains($lower, 'telefono') || str_contains($lower, 'phone') || str_contains($lower, 'celular')) {
            return $this->maskPhone($value) ?? '999999999';
        }
        if (
            str_contains($lower, 'name') || str_contains($lower, 'nombre') ||
            str_contains($lower, 'cliente') || str_contains($lower, 'titular')
        ) {
            return 'CLIENTE_000001';
        }
        if (
            str_contains($lower, 'message') || str_contains($lower, 'mensaje') ||
            str_contains($lower, 'title') || str_contains($lower, 'titulo') ||
            str_contains($lower, 'descripcion') || str_contains($lower, 'observacion')
        ) {
            return 'Notificacion sintetica de staging';
        }
        if (str_contains($lower, 'url') || str_contains($lower, 'link')) {
            return '/staging/notificacion';
        }
        if (str_contains($lower, 'operacion')) {
            return $this->maskOperation($value) ?? '000000';
        }
        if (str_contains($lower, 'cuenta')) {
            return $this->maskAccount($value) ?? '000000';
        }
        if (in_array($lower, ['action_label', 'accion'], true)) {
            return 'Accion sintetica';
        }
        if (in_array($lower, [
            'type', 'module', 'modulo', 'estado', 'status',
            'categoria', 'category', 'color', 'icon',
        ], true)) {
            return $value;
        }

        return 'TEXTO_MASCARA';
    }
}
