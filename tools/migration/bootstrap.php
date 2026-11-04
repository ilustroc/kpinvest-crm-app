<?php

declare(strict_types=1);

/**
 * Utilidades compartidas del ETL de staging.
 *
 * Seguridad:
 *  - No imprime contrasenas, claves HMAC ni valores de datos.
 *  - Solo acepta bases kp_crm_* del staging y el host staging-db.
 *  - La clave HMAC se lee de un secreto montado, nunca de la base ni del repo.
 */

const MIGRATION_ALLOWED_ROLES = ['administrador', 'supervisor', 'asesor', 'soporte'];

function env_value(string $key, ?string $default = null): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Falta la variable de entorno {$key}");
    }

    return $value;
}

function staging_databases(): array
{
    return [
        'reference' => env_value('DB_REFERENCE_DATABASE'),
        'masked' => env_value('DB_MASKED_DATABASE'),
        'work' => env_value('DB_MASK_WORK_DATABASE'),
        'validation' => env_value('DB_VALIDATION_DATABASE'),
        'rehearsal' => env_value('DB_REHEARSAL_DATABASE'),
    ];
}

function assert_staging_environment(): void
{
    if (env_value('MASK_ENV') !== 'staging') {
        throw new RuntimeException('MASK_ENV debe ser exactamente staging');
    }

    if (env_value('DB_HOST') !== 'staging-db') {
        throw new RuntimeException('DB_HOST debe ser staging-db; no se permite el host local ni produccion');
    }

    if (env_value('DB_REFERENCE_HOST') !== 'staging-ref-db') {
        throw new RuntimeException('DB_REFERENCE_HOST debe ser staging-ref-db; la referencia vive en un servidor aislado');
    }

    if (env_value('DB_REFERENCE_HOST') === env_value('DB_HOST')) {
        throw new RuntimeException('La referencia y la base de proceso deben estar en servidores distintos');
    }

    if (!in_array(env_value('DB_ROLE'), ['ro', 'mask'], true)) {
        throw new RuntimeException('DB_ROLE debe ser ro o mask');
    }

    $prohibited = [
        'u480021566_kpinvest_bd',
        'kpinvest_v3_migrate_test',
        'kp_crm_local',
        'mysql',
        'information_schema',
        'performance_schema',
        'sys',
    ];

    foreach (staging_databases() as $label => $database) {
        if (!preg_match('/^kp_crm_[a-z0-9_]+$/', $database)) {
            throw new RuntimeException("Nombre de base no permitido para {$label}: {$database}");
        }
        if (in_array($database, $prohibited, true)) {
            throw new RuntimeException("Base prohibida para staging: {$database}");
        }
    }

    $databases = array_values(staging_databases());
    if (count(array_unique($databases)) !== count($databases)) {
        throw new RuntimeException('Las bases de referencia, enmascarada, trabajo, validacion y ensayo deben ser distintas');
    }

    if (env_value('MASK_KEY_FILE') !== '/run/secrets/mask_hmac_key') {
        throw new RuntimeException('MASK_KEY_FILE debe apuntar al secreto /run/secrets/mask_hmac_key');
    }

    if (strlen(env_value('STAGING_TEST_PASSWORD')) < 12) {
        throw new RuntimeException('STAGING_TEST_PASSWORD debe tener al menos 12 caracteres');
    }
}

function db_credentials(): array
{
    $role = env_value('DB_ROLE');
    $expected = [
        'ro' => 'kp_stage_ro',
        'mask' => 'kp_stage_mask',
    ];
    if (!isset($expected[$role])) {
        throw new RuntimeException("Rol de base no permitido: {$role}");
    }

    $user = env_value('DB_USERNAME');
    if ($user !== $expected[$role]) {
        throw new RuntimeException("DB_USERNAME no corresponde al rol {$role}");
    }

    return [$user, env_value('DB_PASSWORD')];
}

function mask_key(): string
{
    $path = env_value('MASK_KEY_FILE');
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('No se puede leer el secreto HMAC de pseudonimizacion');
    }

    $key = trim((string) file_get_contents($path));
    if (strlen($key) < 32) {
        throw new RuntimeException('La clave HMAC debe tener al menos 32 caracteres');
    }

    return $key;
}

function db_connect(string $database, string $hostKey = 'DB_HOST', string $portKey = 'DB_PORT'): PDO
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new RuntimeException('Nombre de base invalido');
    }

    [$user, $password] = db_credentials();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env_value($hostKey),
        env_value($portKey, '3306'),
        $database
    );

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
}

function ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException("Identificador SQL invalido: {$name}");
    }

    return '`' . $name . '`';
}

function expected_tables(): array
{
    $tables = [
        'asignar_clientes',
        'ccd_clientes',
        'cliente_bloqueos',
        'clientes_cuentas',
        'cna_solicitudes',
        'migrations',
        'notifications',
        'pagos_lotes',
        'pagos_propia',
        'personal_access_tokens',
        'promesa_cuotas',
        'promesa_operaciones',
        'promesas_pago',
        'users',
    ];
    sort($tables);

    return $tables;
}

function list_tables(PDO $pdo, string $database): array
{
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );
    $stmt->execute([$database]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function table_exists(PDO $pdo, string $database, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([$database, $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function table_columns(PDO $pdo, string $database, string $table): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$database, $table]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function row_count(PDO $pdo, string $database, string $table): int
{
    return (int) $pdo->query(
        'SELECT COUNT(*) FROM ' . ident($database) . '.' . ident($table)
    )->fetchColumn();
}

function database_table_count(PDO $pdo, string $database): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"
    );
    $stmt->execute([$database]);

    return (int) $stmt->fetchColumn();
}

function require_empty_database(PDO $pdo, string $database, string $label): void
{
    $count = database_table_count($pdo, $database);
    if ($count !== 0) {
        throw new RuntimeException(
            "La base {$label} ({$database}) debe estar vacia antes de empezar; tiene {$count} tablas"
        );
    }
}

function schema_objects_count(PDO $pdo, string $database, string $type): int
{
    $queries = [
        'triggers' => 'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?',
        'views' => 'SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ?',
        'routines' => 'SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?',
        'events' => 'SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?',
    ];
    if (!isset($queries[$type])) {
        throw new RuntimeException("Tipo de objeto no soportado: {$type}");
    }

    $stmt = $pdo->prepare($queries[$type]);
    $stmt->execute([$database]);

    return (int) $stmt->fetchColumn();
}

function assert_reference_schema(PDO $pdo, bool $required): array
{
    $databases = staging_databases();
    $reference = $databases['reference'];
    $tables = list_tables($pdo, $reference);
    sort($tables);

    if (count($tables) === 0) {
        if ($required) {
            throw new RuntimeException('La base de referencia esta vacia; restaure el dump autorizado primero');
        }

        return ['ready' => false, 'tables' => 0, 'columns' => 0];
    }

    if ($tables !== expected_tables()) {
        throw new RuntimeException(
            'El esquema de referencia no coincide con las 14 tablas esperadas. ' .
            'Detenga la migracion y revise el dump.'
        );
    }

    $columns = 0;
    foreach ($tables as $table) {
        $columns += count(table_columns($pdo, $reference, $table));
    }

    if ($columns !== 153) {
        throw new RuntimeException("El esquema de referencia tiene {$columns} columnas; se esperaban 153");
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND ENGINE <> 'InnoDB'"
    );
    $stmt->execute([$reference]);
    if ((int) $stmt->fetchColumn() !== 0) {
        throw new RuntimeException('Todas las tablas de referencia deben ser InnoDB');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_COLLATION <> 'utf8mb4_unicode_ci'"
    );
    $stmt->execute([$reference]);
    if ((int) $stmt->fetchColumn() !== 0) {
        throw new RuntimeException('Todas las tablas de referencia deben usar utf8mb4_unicode_ci');
    }

    foreach (['triggers', 'views', 'routines', 'events'] as $type) {
        $count = schema_objects_count($pdo, $reference, $type);
        if ($count !== 0) {
            throw new RuntimeException("La referencia contiene {$count} {$type}; detenga y revise");
        }
    }

    return ['ready' => true, 'tables' => count($tables), 'columns' => $columns];
}

function server_info(PDO $pdo): array
{
    $row = $pdo->query(
        "SELECT VERSION() AS version,
                @@sql_mode AS sql_mode,
                @@character_set_server AS charset_server,
                @@collation_server AS collation_server,
                @@lower_case_table_names AS lower_case_table_names,
                @@max_allowed_packet AS max_allowed_packet,
                @@time_zone AS time_zone,
                CURRENT_USER() AS current_user_name"
    )->fetch();

    if (!is_array($row)) {
        throw new RuntimeException('No se pudo leer la configuracion del servidor MariaDB');
    }

    return $row;
}

function assert_server_compatibility(PDO $pdo): array
{
    $info = server_info($pdo);
    if (!str_starts_with((string) $info['version'], '11.8.')) {
        throw new RuntimeException('MariaDB debe ser 11.8.x; version observada: ' . $info['version']);
    }
    if ($info['charset_server'] !== 'utf8mb4') {
        throw new RuntimeException('character_set_server debe ser utf8mb4');
    }
    if ($info['collation_server'] !== 'utf8mb4_unicode_ci') {
        throw new RuntimeException('collation_server debe ser utf8mb4_unicode_ci');
    }
    if ((string) $info['lower_case_table_names'] !== '0') {
        throw new RuntimeException('lower_case_table_names debe ser 0');
    }
    if ((int) $info['max_allowed_packet'] < 67108864) {
        throw new RuntimeException('max_allowed_packet debe ser al menos 64M');
    }

    $requiredModes = [
        'ONLY_FULL_GROUP_BY',
        'STRICT_TRANS_TABLES',
        'NO_ZERO_IN_DATE',
        'NO_ZERO_DATE',
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ENGINE_SUBSTITUTION',
    ];
    $modes = array_map('trim', explode(',', (string) $info['sql_mode']));
    $missing = array_values(array_diff($requiredModes, $modes));
    if ($missing !== []) {
        throw new RuntimeException('sql_mode incompleto; faltan: ' . implode(', ', $missing));
    }

    if (str_starts_with((string) $info['current_user_name'], 'root@')) {
        throw new RuntimeException('El ETL no debe ejecutarse como root');
    }

    $grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($grants as $grant) {
        if (preg_match('/ON\s+\*\.\*/i', (string) $grant) && !preg_match('/\bUSAGE\b/i', (string) $grant)) {
            throw new RuntimeException('El usuario ETL tiene privilegios globales; revise los GRANT');
        }
    }

    return $info;
}

function assert_allowed_roles(PDO $pdo, string $database): array
{
    $stmt = $pdo->query(
        'SELECT role, COUNT(*) AS total FROM ' . ident($database) . '.users GROUP BY role ORDER BY role'
    );
    $roles = [];
    foreach ($stmt->fetchAll() as $row) {
        $role = (string) $row['role'];
        if (!in_array($role, MIGRATION_ALLOWED_ROLES, true)) {
            throw new RuntimeException("Rol incompatible en la copia: {$role}. Detenga la migracion y solicite decision explicita.");
        }
        $roles[$role] = (int) $row['total'];
    }

    return $roles;
}

function hmac_hex(string $domain, string $value, string $key): string
{
    return hash_hmac('sha256', $domain . "\0" . $value, $key);
}

function normalize_digits(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value);
    if ($digits !== null && $digits !== '') {
        return $digits;
    }

    return trim($value);
}

function report_write(string $name, array $data): string
{
    $dir = env_value('REPORT_DIR', '/reports');
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de reportes');
    }

    $payload = [
        'generated_at_utc' => gmdate('c'),
        'environment' => 'staging',
    ] + $data;

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo codificar el reporte JSON');
    }

    $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($path, $json . PHP_EOL) === false) {
        throw new RuntimeException('No se pudo escribir el reporte de staging');
    }

    return $path;
}

function json_out(array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo codificar la salida JSON');
    }
    echo $json . PHP_EOL;
}
