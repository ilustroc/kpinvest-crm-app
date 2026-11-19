<?php

namespace App\Services\Ccd;

use DomainException;
use PDO;
use RuntimeException;
use Throwable;

class CcdProductionMigratorService
{
    public const EXPECTED_TOTAL_CCD = 275987;

    public const EXPECTED_TOTAL_CUENTAS = 282595;

    public const EXPECTED_AQP4_TOTAL = 7024;

    public const EXPECTED_AQP5_TOTAL = 3200;

    public const EXPECTED_AQP_TOTAL = 10224;

    public const EXPECTED_V3_METHOD_TOTAL = 1;

    public const EXPECTED_V5_METHOD_TOTAL = 10223;

    public const METHOD_V3 = 'operacion_pdf_aqp_auditada_v3';

    public const METHOD_V5 = 'operacion_pdf_aqp_auditada_v5';

    public const EXPECTED_CCD_AQP_SHA256 = '861b054ff5ca5027f927d95d8c9980381bb6d3cd69f325ec019b3650506b568b';

    public const EXPECTED_CUENTAS_SHA256 = '0a66ff254fd82cfa021cca872404686ee15785d8231e34eae0ae8711076bbd6f';

    public const REQUIRED_FREE_SPACE_BYTES = 2811445248; // ~2.618 GiB

    public const MIN_FREE_SPACE_BYTES = 5368709120; // 5.0 GiB margen de seguridad

    public const DEFAULT_BATCH_SIZE = 500;

    /**
     * @param  array{
     *   source_pdo: PDO,
     *   target_pdo: PDO,
     *   source_storage: string,
     *   target_storage: string,
     *   apply?: bool,
     *   batch_size?: int,
     *   skip_files?: bool,
     *   manifest_path?: ?string,
     *   expected_total?: ?int,
     *   logger?: ?callable(string): void,
     *   source_reconnector?: ?callable(): PDO,
     *   target_reconnector?: ?callable(): PDO,
     *   source_credentials?: ?array{host?: string, port?: int|string, database?: string, username?: string, password?: string},
     *   target_credentials?: ?array{host?: string, port?: int|string, database?: string, username?: string, password?: string}
     * }  $config
     * @return array<string, mixed>
     */
    public function migrate(array $config): array
    {
        $sourcePdo = $config['source_pdo'];
        $targetPdo = $config['target_pdo'];
        $sourceStorage = rtrim($config['source_storage'], '/\\');
        $targetStorage = rtrim($config['target_storage'], '/\\');
        $apply = (bool) ($config['apply'] ?? false);
        $batchSize = max(1, (int) ($config['batch_size'] ?? self::DEFAULT_BATCH_SIZE));
        $skipFiles = (bool) ($config['skip_files'] ?? false);
        $manifestPath = $config['manifest_path'] ?? null;
        $expectedTotal = isset($config['expected_total']) ? (int) $config['expected_total'] : self::EXPECTED_AQP_TOTAL;
        $logger = $config['logger'] ?? static function (string $msg): void {};

        $sourceReconnector = $config['source_reconnector'] ?? null;
        $targetReconnector = $config['target_reconnector'] ?? null;
        $sourceCredentials = $config['source_credentials'] ?? null;
        $targetCredentials = $config['target_credentials'] ?? null;

        $tMigrateStart = microtime(true);

        // Inspección de esquema destino
        $targetSchema = $this->inspectTargetSchema($targetPdo);

        if ($apply && ! $targetSchema['is_ready_for_apply']) {
            throw new DomainException(
                'No se puede ejecutar --apply: el esquema de destino no tiene las tablas o columnas CCD necesarias. '
                .'Ejecute primero las migraciones selectivas antes de aplicar.'
            );
        }

        $logger('Iniciando verificación preflight de bases de datos...');
        $tPreStart = microtime(true);
        $preflight = $this->validatePreflight($sourcePdo, $targetPdo, $logger, $expectedTotal);
        $tPreElapsed = round(microtime(true) - $tPreStart, 2);
        $logger("Preflight completado conforme en {$tPreElapsed}s.");

        if (! $skipFiles) {
            $logger('Verificando espacio libre en disco de destino...');
            $this->checkDiskSpace($targetStorage, self::MIN_FREE_SPACE_BYTES, $apply);
        }

        // =========================================================================
        // MODO DRY-RUN: Simulación estricta sin escrituras
        // =========================================================================
        if (! $apply) {
            $logger('Modo DRY-RUN activo: ejecutando simulación integral sin escrituras...');

            if (! $targetSchema['is_ready_for_apply']) {
                $logger('  [AVISO ESQUEMA] El esquema destino carece de tablas/columnas CCD nuevas:');
                foreach ($targetSchema['pending_migrations'] as $pending) {
                    $logger("    - Pendiente: {$pending}");
                }
                $logger('  Estas migraciones se aplicarán de forma selectiva durante el modo --apply.');
            } else {
                $logger('  [INFO ESQUEMA] El esquema destino ya contiene todas las tablas y columnas CCD.');
            }

            $filesVerified = 0;
            if (! $skipFiles) {
                $logger('  Verificando existencia e integridad de los PDFs en almacenamiento origen...');
                $tFilesStart = microtime(true);
                $fileResults = $this->transferAndVerifyFiles(
                    $sourcePdo,
                    $sourceStorage,
                    $targetStorage,
                    false, // dry-run: no escribe en destino
                    $logger,
                    $expectedTotal
                );
                $filesVerified = $fileResults['verified'];
                $tFilesElapsed = round(microtime(true) - $tFilesStart, 2);
                $logger("  PDFs en origen verificados conforme: {$filesVerified} ({$tFilesElapsed}s)");

                // RENOVACIÓN CONTROLADA DE AMBAS CONEXIONES TRAS OPERACIÓN PROLONGADA DE FILESYSTEM
                $logger('  Renovando controladamente conexiones MySQL tras procesamiento prolongado de PDFs...');
                $this->refreshPdoConnection($sourcePdo, $sourceReconnector, $sourceCredentials, $logger, 'conexión origen (staging)');
                $this->refreshPdoConnection($targetPdo, $targetReconnector, $targetCredentials, $logger, 'conexión destino (producción)');
            }

            $logger('  Validando referencias y coherencia cruzada de DNI entre tablas...');
            $tValStart = microtime(true);
            $this->validateAssociationsPreInsert($sourcePdo, $targetPdo, $expectedTotal, $batchSize, $logger);
            $tValElapsed = round(microtime(true) - $tValStart, 2);
            $logger("  Validación de asociaciones completada en {$tValElapsed}s.");

            $archivosEligible = $this->migrateCcdArchivos($sourcePdo, $targetPdo, false, $batchSize, $logger, $expectedTotal, $targetReconnector, $targetCredentials);
            $linksEligible = $this->linkCcdArchivoId($sourcePdo, $targetPdo, false, $batchSize, $logger, $expectedTotal, $targetReconnector, $targetCredentials);
            $associationsEligible = $this->migrateAssociations($sourcePdo, $targetPdo, false, $batchSize, $logger, $expectedTotal, $targetReconnector, $targetCredentials);

            $tTotalSim = round(microtime(true) - $tMigrateStart, 2);
            $logger("Simulación DRY-RUN completada con éxito en {$tTotalSim}s. CERO escrituras realizadas en destino.");

            return [
                'status' => 'simulated',
                'mode' => 'dry-run',
                'preflight' => $preflight,
                'target_schema' => $targetSchema,
                'files_transferred' => 0,
                'files_verified' => $filesVerified,
                'archivos_migrated' => 0,
                'archivos_eligible' => $archivosEligible,
                'links_updated' => 0,
                'links_eligible' => $linksEligible,
                'associations_migrated' => 0,
                'associations_eligible' => $associationsEligible,
                'audit' => [
                    'passed' => true,
                    'dry_run' => true,
                    'pending_migrations' => $targetSchema['pending_migrations'],
                    'notes' => 'Simulación conforme sin escrituras.',
                ],
                'manifest_path' => null,
                'manifest_sha256' => null,
            ];
        }

        // =========================================================================
        // MODO APPLY: Ejecución real autorizada
        // =========================================================================
        $logger('Modo APPLY activo: iniciando ejecución autorizada...');

        if (! $targetSchema['is_ready_for_apply']) {
            throw new DomainException(
                'No se puede ejecutar --apply: el esquema de destino no tiene las tablas o columnas CCD necesarias. '
                .'Ejecute primero las migraciones selectivas antes de aplicar.'
            );
        }

        $filesTransferred = 0;
        $filesVerified = 0;

        if (! $skipFiles) {
            $logger('Iniciando transferencia y verificación de PDFs AQP...');
            $tFilesStart = microtime(true);
            $fileResults = $this->transferAndVerifyFiles(
                $sourcePdo,
                $sourceStorage,
                $targetStorage,
                true,
                $logger,
                $expectedTotal
            );
            $filesTransferred = $fileResults['transferred'];
            $filesVerified = $fileResults['verified'];
            $tFilesElapsed = round(microtime(true) - $tFilesStart, 2);
            $logger("Transferencia de PDFs completada: {$filesTransferred} transferidos, {$filesVerified} verificados ({$tFilesElapsed}s).");

            // RENOVACIÓN CONTROLADA DE AMBAS CONEXIONES TRAS OPERACIÓN PROLONGADA DE FILESYSTEM
            $logger('Renovando controladamente conexiones MySQL tras transferencia y verificación prolongada de PDFs...');
            $this->refreshPdoConnection($sourcePdo, $sourceReconnector, $sourceCredentials, $logger, 'conexión origen (staging)');
            $this->refreshPdoConnection($targetPdo, $targetReconnector, $targetCredentials, $logger, 'conexión destino (producción)');
        }

        // Validación estricta de coherencia antes de cualquier inserción en producción
        $logger('Validando coherencia de asociaciones antes de persistencia...');
        $tValStart = microtime(true);
        $this->validateAssociationsPreInsert($sourcePdo, $targetPdo, $expectedTotal, $batchSize, $logger);
        $tValElapsed = round(microtime(true) - $tValStart, 2);
        $logger("Validación previa de asociaciones conforme en {$tValElapsed}s.");

        $logger('Procesando migración de registros ccd_archivos (rechazo ante discrepancia)...');
        $archivosMigrated = $this->migrateCcdArchivos($sourcePdo, $targetPdo, true, $batchSize, $logger, $expectedTotal, $targetReconnector, $targetCredentials);

        $logger('Actualizando vinculación ccd_clientes.ccd_archivo_id...');
        $linksUpdated = $this->linkCcdArchivoId($sourcePdo, $targetPdo, true, $batchSize, $logger, $expectedTotal, $targetReconnector, $targetCredentials);

        $logger('Migrando asociaciones confirmadas a ccd_cliente_cuenta (sin sobrescritura ciega)...');
        $associationsMigrated = $this->migrateAssociations($sourcePdo, $targetPdo, true, $batchSize, $logger, $expectedTotal, $targetReconnector, $targetCredentials);

        $logger('Ejecutando auditoría integral post-migración...');
        $tAudStart = microtime(true);
        $audit = $this->auditTarget($targetPdo, $targetStorage, $skipFiles, $expectedTotal, $logger);
        $tAudElapsed = round(microtime(true) - $tAudStart, 2);
        $logger("Auditoría post-migración finalizada en {$tAudElapsed}s.");

        // RENOVACIÓN CONTROLADA DE AMBAS CONEXIONES TRAS AUDITORÍA FÍSICA PROLONGADA (HOSTINGER wait_timeout=20)
        $logger('Renovando controladamente conexiones MySQL tras auditoría física antes de generar manifiesto...');
        $this->refreshPdoConnection($sourcePdo, $sourceReconnector, $sourceCredentials, $logger, 'conexión origen (staging)');
        $this->refreshPdoConnection($targetPdo, $targetReconnector, $targetCredentials, $logger, 'conexión destino (producción)');

        $manifestSha256 = null;
        if ($manifestPath !== null) {
            $logger('Generando manifiesto criptográfico de migración...');
            $manifestSha256 = $this->generateManifest($sourcePdo, $targetPdo, $manifestPath, $expectedTotal);
            $logger("Manifiesto generado con SHA-256: {$manifestSha256}");
        }

        $tTotalApply = round(microtime(true) - $tMigrateStart, 2);
        $logger("Proceso completado con éxito en {$tTotalApply}s.");

        return [
            'status' => 'success',
            'mode' => 'apply',
            'preflight' => $preflight,
            'target_schema' => $targetSchema,
            'files_transferred' => $filesTransferred,
            'files_verified' => $filesVerified,
            'archivos_migrated' => $archivosMigrated,
            'links_updated' => $linksUpdated,
            'associations_migrated' => $associationsMigrated,
            'audit' => $audit,
            'manifest_path' => $manifestPath,
            'manifest_sha256' => $manifestSha256,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectTargetSchema(PDO $targetPdo): array
    {
        $hasArchivos = $this->tableExists($targetPdo, 'ccd_archivos');
        $hasAssoc = $this->tableExists($targetPdo, 'ccd_cliente_cuenta');
        $hasArchivoIdCol = $this->columnExists($targetPdo, 'ccd_clientes', 'ccd_archivo_id');
        $hasMigrationsTable = $this->tableExists($targetPdo, 'migrations');

        $pendingMigrations = [];
        if ($hasMigrationsTable) {
            $mig1 = (int) $this->fetchScalar($targetPdo, "SELECT COUNT(*) FROM migrations WHERE migration LIKE '%create_ccd_archivos_and_link_ccd_clientes%'");
            if ($mig1 === 0) {
                $pendingMigrations[] = '2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php';
            }
            $mig2 = (int) $this->fetchScalar($targetPdo, "SELECT COUNT(*) FROM migrations WHERE migration LIKE '%create_ccd_cliente_cuenta_table%'");
            if ($mig2 === 0) {
                $pendingMigrations[] = '2026_11_13_000001_create_ccd_cliente_cuenta_table.php';
            }
        } else {
            $pendingMigrations = [
                '2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php',
                '2026_11_13_000001_create_ccd_cliente_cuenta_table.php',
            ];
        }

        return [
            'has_ccd_archivos' => $hasArchivos,
            'has_ccd_cliente_cuenta' => $hasAssoc,
            'has_ccd_archivo_id_col' => $hasArchivoIdCol,
            'has_ccd_archivo_id_column' => $hasArchivoIdCol,
            'pending_migrations' => $pendingMigrations,
            'is_ready_for_apply' => $hasArchivos && $hasAssoc && $hasArchivoIdCol,
        ];
    }

    /**
     * Crea una conexión PDO configurada de forma segura para MySQL.
     *
     * @param  array{host?: string, port?: int|string, database?: string, username?: string, password?: string}  $creds
     */
    public function createPdoConnection(array $creds): PDO
    {
        $host = (string) ($creds['host'] ?? '127.0.0.1');
        $port = (int) ($creds['port'] ?? 3306);
        $database = (string) ($creds['database'] ?? '');
        $username = (string) ($creds['username'] ?? '');
        $password = (string) ($creds['password'] ?? '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30,
        ];

        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * Comprueba si una conexión PDO está activa mediante un ping no intrusivo (SELECT 1).
     */
    public function pingPdo(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query('SELECT 1');
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();

                return true;
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Renueva controladamente una conexión PDO tras operaciones prolongadas de filesystem,
     * utilizando un reconnector o las credenciales privadas ya cargadas en memoria.
     * Rechaza terminantemente renovaciones a ciegas si existe una transacción abierta.
     *
     * @param  array{host?: string, port?: int|string, database?: string, username?: string, password?: string}|null  $credentials
     */
    public function refreshPdoConnection(
        PDO &$pdo,
        ?callable $reconnector = null,
        ?array $credentials = null,
        ?callable $logger = null,
        string $name = 'conexión'
    ): PDO {
        $log = $logger ?? static function (string $msg): void {};

        // 1. Evitar reintentos ciegos dentro de transacciones con estado de commit incierto
        try {
            if ($pdo->inTransaction()) {
                throw new DomainException("No se puede renovar {$name} mientras existe una transacción abierta. Se requiere reversión o aborto explícito.");
            }
        } catch (Throwable $e) {
            if ($e instanceof DomainException) {
                throw $e;
            }
            // Si inTransaction() falla debido a que el servidor cerró la conexión (2006/2013),
            // no hay transacción confirmable activa en el servidor.
        }

        // 2. Si se suministró un reconnector explícito, invocarlo
        if ($reconnector !== null) {
            $log("Renovando controladamente {$name} tras operación prolongada...");
            $newPdo = $reconnector();
            if (! $newPdo instanceof PDO) {
                throw new RuntimeException("El reconnector para {$name} no devolvió una instancia válida de PDO.");
            }
            $pdo = $newPdo;

            return $pdo;
        }

        // 3. Si se suministraron credenciales cargadas, crear nueva instancia PDO
        if ($credentials !== null) {
            $log("Renovando controladamente {$name} con credenciales cargadas tras operación prolongada...");
            $pdo = $this->createPdoConnection($credentials);

            return $pdo;
        }

        // 4. Si no hay reconnector ni credenciales, verificar con ping
        if (! $this->pingPdo($pdo)) {
            throw new DomainException(
                "La {$name} MySQL se ha cerrado tras una operación prolongada (error 2006/2013). "
                .'Proporcione reconnector o credenciales cargadas para renovación automática.'
            );
        }

        return $pdo;
    }

    /**
     * Asegura que una conexión PDO permanezca activa antes de iniciar transacciones,
     * renovándola controladamente si el ping detecta que se ha cerrado.
     *
     * @param  array{host?: string, port?: int|string, database?: string, username?: string, password?: string}|null  $credentials
     */
    public function ensurePdoConnection(
        PDO &$pdo,
        ?callable $reconnector = null,
        ?array $credentials = null,
        ?callable $logger = null,
        string $name = 'conexión'
    ): PDO {
        if (! $this->pingPdo($pdo)) {
            return $this->refreshPdoConnection($pdo, $reconnector, $credentials, $logger, $name);
        }

        return $pdo;
    }

    /**
     * Instala y valida exclusivamente los archivos de migración selectivos en destino
     * antes de ejecutar 'php artisan migrate --path=...'.
     *
     * @return array<string, array<string, string>>
     */
    public function installMigrationFiles(
        string $sourceDir,
        string $targetDir,
        bool $apply,
        ?string $backupDir = null,
        ?callable $logger = null
    ): array {
        $log = $logger ?? static function (string $msg): void {};
        $sourceDir = rtrim($sourceDir, '/\\');
        $targetDir = rtrim($targetDir, '/\\');
        $backupDir = $backupDir !== null ? rtrim($backupDir, '/\\') : null;
        $results = [];

        $migrationFiles = [
            'database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php',
            'database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php',
        ];

        foreach ($migrationFiles as $relPath) {
            $srcFile = $sourceDir.'/'.$relPath;
            $tgtFile = $targetDir.'/'.$relPath;

            if (! is_file($srcFile)) {
                throw new DomainException("Archivo de migración fuente requerido no existe: {$srcFile}");
            }

            $this->assertPhpSyntax($srcFile);

            if (! file_exists($tgtFile)) {
                if ($apply) {
                    $dir = dirname($tgtFile);
                    if (! is_dir($dir)) {
                        @mkdir($dir, 0775, true);
                    }
                    if (! @copy($srcFile, $tgtFile)) {
                        throw new RuntimeException("Error al copiar archivo de migración: {$tgtFile}");
                    }
                    @chmod($tgtFile, 0664);
                    $this->assertPhpSyntax($tgtFile);
                    $log("Instalado archivo de migración conforme: {$relPath}");
                    $results[$relPath] = ['status' => 'installed', 'message' => 'Archivo de migración instalado'];
                } else {
                    $log("[DRY-RUN] Archivo de migración pendiente de instalación: {$relPath}");
                    $results[$relPath] = ['status' => 'pending_install', 'message' => 'Migración pendiente de instalar'];
                }
            } else {
                $srcSha = hash_file('sha256', $srcFile);
                $tgtSha = hash_file('sha256', $tgtFile);

                if (hash_equals((string) $srcSha, (string) $tgtSha)) {
                    $log("Archivo de migración idéntico e intacto: {$relPath}");
                    $results[$relPath] = ['status' => 'identical', 'message' => 'Migración idéntica ya presente'];
                } else {
                    if ($apply) {
                        if ($backupDir !== null) {
                            $bakFile = $backupDir.'/modified_files/'.$relPath;
                            @mkdir(dirname($bakFile), 0775, true);
                            @copy($tgtFile, $bakFile);
                        }
                        if (! @copy($srcFile, $tgtFile)) {
                            throw new RuntimeException("Error al actualizar archivo de migración con respaldo: {$tgtFile}");
                        }
                        @chmod($tgtFile, 0664);
                        $this->assertPhpSyntax($tgtFile);
                        $log("Actualizado archivo de migración con respaldo individual: {$relPath}");
                        $results[$relPath] = ['status' => 'updated_with_backup', 'message' => 'Migración actualizada con respaldo'];
                    } else {
                        $log("[DRY-RUN] Archivo de migración difiere en destino; se respaldará individualmente en --apply: {$relPath}");
                        $results[$relPath] = ['status' => 'differs', 'message' => 'Migración difiere, elegible para actualización'];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function validatePreflight(
        PDO $sourcePdo,
        PDO $targetPdo,
        ?callable $logger = null,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL
    ): array {
        $log = $logger ?? static function (string $msg): void {};

        // 1. Tablas requeridas (solo las existentes de línea base en destino)
        $this->assertTableExists($sourcePdo, 'ccd_clientes', 'origen');
        $this->assertTableExists($sourcePdo, 'clientes_cuentas', 'origen');
        $this->assertTableExists($sourcePdo, 'ccd_archivos', 'origen');
        $this->assertTableExists($sourcePdo, 'ccd_cliente_cuenta', 'origen');

        $this->assertTableExists($targetPdo, 'ccd_clientes', 'destino');
        $this->assertTableExists($targetPdo, 'clientes_cuentas', 'destino');

        $srcCcdCount = (int) $this->fetchScalar($sourcePdo, 'SELECT COUNT(*) FROM ccd_clientes');
        $tgtCcdCount = (int) $this->fetchScalar($targetPdo, 'SELECT COUNT(*) FROM ccd_clientes');
        $srcCuentasCount = (int) $this->fetchScalar($sourcePdo, 'SELECT COUNT(*) FROM clientes_cuentas');
        $tgtCuentasCount = (int) $this->fetchScalar($targetPdo, 'SELECT COUNT(*) FROM clientes_cuentas');

        // Modo adaptativo para pruebas con fixtures personalizados
        if ($expectedTotal !== null && $expectedTotal !== self::EXPECTED_AQP_TOTAL) {
            if ($srcCcdCount !== $expectedTotal || $tgtCcdCount !== $expectedTotal) {
                throw new DomainException(
                    "Discrepancia en conteo total de ccd_clientes (origen: {$srcCcdCount}, destino: {$tgtCcdCount}; esperado: {$expectedTotal})."
                );
            }

            if ($srcCuentasCount !== $expectedTotal || $tgtCuentasCount !== $expectedTotal) {
                throw new DomainException(
                    "Discrepancia en conteo total de clientes_cuentas (origen: {$srcCuentasCount}, destino: {$tgtCuentasCount}; esperado: {$expectedTotal})."
                );
            }

            $srcCcdSha = $this->calculateCcdAqpSha256($sourcePdo);
            $tgtCcdSha = $this->calculateCcdAqpSha256($targetPdo);

            if (! hash_equals($srcCcdSha, $tgtCcdSha)) {
                throw new DomainException('Las firmas SHA-256 de ccd_clientes AQP no coinciden entre origen y destino.');
            }

            $srcCuentasSha = $this->calculateCuentasSha256($sourcePdo);
            $tgtCuentasSha = $this->calculateCuentasSha256($targetPdo);

            if (! hash_equals($srcCuentasSha, $tgtCuentasSha)) {
                throw new DomainException('Las firmas SHA-256 de clientes_cuentas no coinciden entre origen y destino.');
            }

            $srcAssocTotal = (int) $this->fetchScalar(
                $sourcePdo,
                "SELECT COUNT(*) FROM ccd_cliente_cuenta cca
                 JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
                 WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada'"
            );

            if ($srcAssocTotal !== $expectedTotal) {
                throw new DomainException("Staging debe tener exactamente {$expectedTotal} asociaciones confirmadas AQP; encontradas: {$srcAssocTotal}.");
            }

            $srcCatalogedFiles = (int) $this->fetchScalar(
                $sourcePdo,
                "SELECT COUNT(DISTINCT cc.ccd_archivo_id) FROM ccd_clientes cc
                 WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cc.ccd_archivo_id IS NOT NULL"
            );

            if ($srcCatalogedFiles !== $expectedTotal) {
                throw new DomainException("Staging debe tener exactamente {$expectedTotal} archivos catalogados; encontrados: {$srcCatalogedFiles}.");
            }

            return [
                'ccd_total' => $srcCcdCount,
                'cuentas_total' => $srcCuentasCount,
                'aqp4_total' => $srcCcdCount,
                'aqp5_total' => 0,
                'ccd_sha256' => $srcCcdSha,
                'cuentas_sha256' => $srcCuentasSha,
                'staging_confirmed_associations' => $srcAssocTotal,
                'staging_v3_method' => 0,
                'staging_v5_method' => $srcAssocTotal,
                'staging_cataloged_files' => $srcCatalogedFiles,
            ];
        }

        // Modo estándar certificado (Línea base completa)
        if ($srcCcdCount !== self::EXPECTED_TOTAL_CCD || $tgtCcdCount !== self::EXPECTED_TOTAL_CCD) {
            throw new DomainException(
                "Discrepancia en conteo total de ccd_clientes (origen: {$srcCcdCount}, destino: {$tgtCcdCount}; esperado: ".self::EXPECTED_TOTAL_CCD.').'
            );
        }

        // 3. Conteo de clientes_cuentas
        $srcCuentasCount = (int) $this->fetchScalar($sourcePdo, 'SELECT COUNT(*) FROM clientes_cuentas');
        $tgtCuentasCount = (int) $this->fetchScalar($targetPdo, 'SELECT COUNT(*) FROM clientes_cuentas');

        if ($srcCuentasCount !== self::EXPECTED_TOTAL_CUENTAS || $tgtCuentasCount !== self::EXPECTED_TOTAL_CUENTAS) {
            throw new DomainException(
                "Discrepancia en conteo total de clientes_cuentas (origen: {$srcCuentasCount}, destino: {$tgtCuentasCount}; esperado: ".self::EXPECTED_TOTAL_CUENTAS.').'
            );
        }

        // 4. Conteo de AQP4 y AQP5 en ccd_clientes
        $srcAqp4 = (int) $this->fetchScalar($sourcePdo, "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP4'");
        $srcAqp5 = (int) $this->fetchScalar($sourcePdo, "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP5'");
        $tgtAqp4 = (int) $this->fetchScalar($targetPdo, "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP4'");
        $tgtAqp5 = (int) $this->fetchScalar($targetPdo, "SELECT COUNT(*) FROM ccd_clientes WHERE cosecha = 'AQP5'");

        if ($srcAqp4 !== self::EXPECTED_AQP4_TOTAL || $tgtAqp4 !== self::EXPECTED_AQP4_TOTAL
            || $srcAqp5 !== self::EXPECTED_AQP5_TOTAL || $tgtAqp5 !== self::EXPECTED_AQP5_TOTAL) {
            throw new DomainException(
                "Discrepancia en conteo AQP de ccd_clientes (origen: AQP4={$srcAqp4}, AQP5={$srcAqp5}; destino: AQP4={$tgtAqp4}, AQP5={$tgtAqp5})."
            );
        }

        // 5. Conteo de CAJAAQP4 y CAJAAQP5 en clientes_cuentas
        $srcCuentasAqp4 = (int) $this->fetchScalar($sourcePdo, "SELECT COUNT(*) FROM clientes_cuentas WHERE cosecha = 'CAJAAQP4'");
        $srcCuentasAqp5 = (int) $this->fetchScalar($sourcePdo, "SELECT COUNT(*) FROM clientes_cuentas WHERE cosecha = 'CAJAAQP5'");
        $tgtCuentasAqp4 = (int) $this->fetchScalar($targetPdo, "SELECT COUNT(*) FROM clientes_cuentas WHERE cosecha = 'CAJAAQP4'");
        $tgtCuentasAqp5 = (int) $this->fetchScalar($targetPdo, "SELECT COUNT(*) FROM clientes_cuentas WHERE cosecha = 'CAJAAQP5'");

        if ($srcCuentasAqp4 !== self::EXPECTED_AQP4_TOTAL || $tgtCuentasAqp4 !== self::EXPECTED_AQP4_TOTAL
            || $srcCuentasAqp5 !== self::EXPECTED_AQP5_TOTAL || $tgtCuentasAqp5 !== self::EXPECTED_AQP5_TOTAL) {
            throw new DomainException(
                "Discrepancia en cuentas CAJAAQP (origen: CAJAAQP4={$srcCuentasAqp4}, CAJAAQP5={$srcCuentasAqp5}; destino: CAJAAQP4={$tgtCuentasAqp4}, CAJAAQP5={$tgtCuentasAqp5})."
            );
        }

        // 6. Firmas SHA-256 de ccd_clientes y clientes_cuentas
        $log('Calculando firmas SHA-256 de tablas CCD y Cuentas (serialización JSON-line ordenada por ID)...');
        $srcCcdSha = $this->calculateCcdAqpSha256($sourcePdo);
        $tgtCcdSha = $this->calculateCcdAqpSha256($targetPdo);

        if (! hash_equals($srcCcdSha, $tgtCcdSha)) {
            throw new DomainException(
                "Las firmas SHA-256 de ccd_clientes AQP no coinciden entre origen ({$srcCcdSha}) y destino ({$tgtCcdSha})."
            );
        }

        if (! hash_equals(self::EXPECTED_CCD_AQP_SHA256, $srcCcdSha)) {
            throw new DomainException(
                "La firma SHA-256 de ccd_clientes AQP ({$srcCcdSha}) difiere de la firma oficial certificada (".self::EXPECTED_CCD_AQP_SHA256.').'
            );
        }

        $srcCuentasSha = $this->calculateCuentasSha256($sourcePdo);
        $tgtCuentasSha = $this->calculateCuentasSha256($targetPdo);

        if (! hash_equals($srcCuentasSha, $tgtCuentasSha)) {
            throw new DomainException(
                "Las firmas SHA-256 de clientes_cuentas no coinciden entre origen ({$srcCuentasSha}) y destino ({$tgtCuentasSha})."
            );
        }

        if (! hash_equals(self::EXPECTED_CUENTAS_SHA256, $srcCuentasSha)) {
            throw new DomainException(
                "La firma SHA-256 de clientes_cuentas ({$srcCuentasSha}) difiere de la firma oficial certificada (".self::EXPECTED_CUENTAS_SHA256.').'
            );
        }

        // 7. Asociaciones en staging
        $srcAssocTotal = (int) $this->fetchScalar(
            $sourcePdo,
            "SELECT COUNT(*) FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada'"
        );

        if ($srcAssocTotal !== self::EXPECTED_AQP_TOTAL) {
            throw new DomainException(
                'Staging debe tener exactamente '.self::EXPECTED_AQP_TOTAL." asociaciones confirmadas AQP; encontradas: {$srcAssocTotal}."
            );
        }

        $srcV3Count = (int) $this->fetchScalar(
            $sourcePdo,
            "SELECT COUNT(*) FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada' AND cca.metodo_verificacion = '".self::METHOD_V3."'"
        );

        $srcV5Count = (int) $this->fetchScalar(
            $sourcePdo,
            "SELECT COUNT(*) FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada' AND cca.metodo_verificacion = '".self::METHOD_V5."'"
        );

        if ($srcV3Count !== self::EXPECTED_V3_METHOD_TOTAL || $srcV5Count !== self::EXPECTED_V5_METHOD_TOTAL) {
            throw new DomainException(
                "Distribución de métodos en staging inconsistente (v3: {$srcV3Count}, v5: {$srcV5Count})."
            );
        }

        // 8. Archivos catalogados en staging
        $srcCatalogedFiles = (int) $this->fetchScalar(
            $sourcePdo,
            "SELECT COUNT(DISTINCT cc.ccd_archivo_id) FROM ccd_clientes cc
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cc.ccd_archivo_id IS NOT NULL"
        );

        if ($srcCatalogedFiles !== self::EXPECTED_AQP_TOTAL) {
            throw new DomainException(
                'Staging debe tener exactamente '.self::EXPECTED_AQP_TOTAL." archivos catalogados en ccd_archivos; encontrados: {$srcCatalogedFiles}."
            );
        }

        return [
            'ccd_total' => $srcCcdCount,
            'cuentas_total' => $srcCuentasCount,
            'aqp4_total' => $srcAqp4,
            'aqp5_total' => $srcAqp5,
            'ccd_sha256' => $srcCcdSha,
            'cuentas_sha256' => $srcCuentasSha,
            'staging_confirmed_associations' => $srcAssocTotal,
            'staging_v3_method' => $srcV3Count,
            'staging_v5_method' => $srcV5Count,
            'staging_cataloged_files' => $srcCatalogedFiles,
        ];
    }

    public function checkDiskSpace(string $targetStoragePath, int $minBytes, bool $createDir = false): void
    {
        $probeDir = $targetStoragePath;
        if (! is_dir($probeDir)) {
            if ($createDir) {
                @mkdir($probeDir, 0770, true);
            } else {
                while (! is_dir($probeDir) && dirname($probeDir) !== $probeDir) {
                    $probeDir = dirname($probeDir);
                }
            }
        }

        $free = @disk_free_space($probeDir);

        if ($free !== false && $free < $minBytes) {
            $freeGiB = round($free / 1_073_741_824, 2);
            $requiredGiB = round($minBytes / 1_073_741_824, 2);
            throw new DomainException("Espacio insuficiente en disco destino ({$probeDir}): {$freeGiB} GiB disponibles, {$requiredGiB} GiB requeridos.");
        }
    }

    /**
     * @return array{transferred: int, verified: int}
     */
    public function transferAndVerifyFiles(
        PDO $sourcePdo,
        string $sourceStorage,
        string $targetStorage,
        bool $apply,
        ?callable $logger = null,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL
    ): array {
        $log = $logger ?? static function (string $msg): void {};

        $stmt = $sourcePdo->query(
            "SELECT ca.id, ca.path, ca.sha256, ca.size_bytes
             FROM ccd_archivos ca
             JOIN ccd_clientes cc ON cc.ccd_archivo_id = ca.id
             WHERE cc.cosecha IN ('AQP4', 'AQP5')
             ORDER BY ca.id"
        );

        // Desacoplar consulta de metadatos del procesamiento de filesystem: cerrar cursor de inmediato
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        unset($stmt);

        $totalFiles = count($files);
        if ($expectedTotal !== null && $totalFiles !== $expectedTotal) {
            throw new DomainException("Metadatos de archivos CCD incompletos: se encontraron {$totalFiles} registros (esperados: {$expectedTotal}).");
        }

        $transferred = 0;
        $verified = 0;
        $index = 0;
        $t0 = microtime(true);
        $lastBatchTime = $t0;

        foreach ($files as $file) {
            $index++;
            $relPath = (string) $file['path'];
            $expectedSha = strtolower((string) $file['sha256']);
            $expectedSize = (int) $file['size_bytes'];

            $srcFile = $sourceStorage.'/'.$relPath;
            $tgtFile = $targetStorage.'/'.$relPath;

            if (! is_file($srcFile)) {
                throw new DomainException("Archivo origen no encontrado en disco: {$srcFile}");
            }

            if (! $apply) {
                // Modo dry-run: verificar integridad en origen únicamente, NO tocar destino
                $srcSha = hash_file('sha256', $srcFile);
                if (! hash_equals($expectedSha, (string) $srcSha)) {
                    throw new DomainException("Huella corrupta en archivo origen {$srcFile} (esperada: {$expectedSha}, calculada: {$srcSha})");
                }
                $verified++;

                if (($index % 1000 === 0 || $index === $totalFiles) && $totalFiles > 0) {
                    $now = microtime(true);
                    $elapsed = round($now - $t0, 2);
                    $batchElapsed = round($now - $lastBatchTime, 2);
                    $lastBatchTime = $now;
                    $log("  PDFs verificados en origen: {$index}/{$totalFiles} ({$elapsed}s acumulados, último bloque en {$batchElapsed}s)");
                }

                continue;
            }

            // Modo apply: verificar destino o copiar de forma segura
            $tgtDir = dirname($tgtFile);
            if (! is_dir($tgtDir) && ! @mkdir($tgtDir, 0770, true) && ! is_dir($tgtDir)) {
                throw new RuntimeException("No se pudo crear directorio destino: {$tgtDir}");
            }

            if (is_file($tgtFile)) {
                $tgtSha = hash_file('sha256', $tgtFile);
                if (hash_equals($expectedSha, (string) $tgtSha)) {
                    // Idempotente: ya existe y la firma es exacta
                    $verified++;

                    if (($index % 1000 === 0 || $index === $totalFiles) && $totalFiles > 0) {
                        $now = microtime(true);
                        $elapsed = round($now - $t0, 2);
                        $batchElapsed = round($now - $lastBatchTime, 2);
                        $lastBatchTime = $now;
                        $log("  PDFs verificados/transferidos: {$index}/{$totalFiles} ({$elapsed}s acumulados, último bloque en {$batchElapsed}s)");
                    }

                    continue;
                }
                // Si existe pero la huella difiere: conflicto estricto
                throw new DomainException(
                    "Conflicto de integridad en archivo destino {$tgtFile}: el archivo ya existe con una firma SHA-256 incompatible (esperada: {$expectedSha}, calculada: {$tgtSha})."
                );
            }

            if (! @copy($srcFile, $tgtFile)) {
                throw new RuntimeException("Error copiando archivo {$srcFile} a {$tgtFile}");
            }

            @chmod($tgtFile, 0660);

            $tgtSha = hash_file('sha256', $tgtFile);
            $tgtSize = filesize($tgtFile);

            if (! hash_equals($expectedSha, (string) $tgtSha) || $tgtSize !== $expectedSize) {
                throw new DomainException("Error de integridad tras copiar {$tgtFile}: SHA-256 o tamaño no coincide.");
            }

            $transferred++;
            $verified++;

            if (($index % 1000 === 0 || $index === $totalFiles) && $totalFiles > 0) {
                $now = microtime(true);
                $elapsed = round($now - $t0, 2);
                $batchElapsed = round($now - $lastBatchTime, 2);
                $lastBatchTime = $now;
                $log("  PDFs transferidos/verificados: {$index}/{$totalFiles} ({$elapsed}s acumulados, último bloque en {$batchElapsed}s)");
            }
        }

        if ($expectedTotal !== null && $index !== $expectedTotal) {
            throw new DomainException("Se procesaron {$index} PDFs en lugar de los {$expectedTotal} esperados.");
        }

        return ['transferred' => $transferred, 'verified' => $verified];
    }

    /**
     * @param  array{host?: string, port?: int|string, database?: string, username?: string, password?: string}|null  $targetCredentials
     */
    public function migrateCcdArchivos(
        PDO $sourcePdo,
        PDO $targetPdo,
        bool $apply,
        int $batchSize,
        ?callable $logger = null,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL,
        ?callable $targetReconnector = null,
        ?array $targetCredentials = null
    ): int {
        $log = $logger ?? static function (string $msg): void {};
        $t0 = microtime(true);

        $stmt = $sourcePdo->query(
            "SELECT ca.id, ca.disk, ca.path, ca.sha256, ca.size_bytes, ca.mime_type, ca.nombre_original, ca.verified_at, ca.created_at, ca.updated_at
             FROM ccd_archivos ca
             JOIN ccd_clientes cc ON cc.ccd_archivo_id = ca.id
             WHERE cc.cosecha IN ('AQP4', 'AQP5')
             ORDER BY ca.id"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        unset($stmt);
        $total = count($rows);

        if ($expectedTotal !== null && $total !== $expectedTotal) {
            throw new DomainException("Se esperaban {$expectedTotal} registros en ccd_archivos, encontrados: {$total}.");
        }

        if (! $apply) {
            return $total;
        }

        $migrated = 0;
        $chunks = array_chunk($rows, $batchSize);
        $totalChunks = count($chunks);

        $checkSql = 'SELECT id, disk, path, sha256, size_bytes, mime_type, nombre_original FROM ccd_archivos WHERE id = ?';
        $insertSql = 'INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        foreach ($chunks as $chunkIndex => $chunk) {
            $bNum = $chunkIndex + 1;
            $bStart = microtime(true);
            $this->ensurePdoConnection($targetPdo, $targetReconnector, $targetCredentials, $logger, 'conexión destino');
            $targetPdo->beginTransaction();
            try {
                $checkStmt = $targetPdo->prepare($checkSql);
                $insertStmt = $targetPdo->prepare($insertSql);

                foreach ($chunk as $row) {
                    $checkStmt->execute([$row['id']]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing !== false) {
                        $isIdentical = $existing['disk'] === $row['disk']
                            && $existing['path'] === $row['path']
                            && strcasecmp((string) $existing['sha256'], (string) $row['sha256']) === 0
                            && (int) $existing['size_bytes'] === (int) $row['size_bytes']
                            && $existing['mime_type'] === $row['mime_type']
                            && $existing['nombre_original'] === $row['nombre_original'];

                        if ($isIdentical) {
                            $migrated++;

                            continue;
                        }

                        throw new DomainException(
                            "Conflicto de integridad en ccd_archivos ID {$row['id']}: el registro existente en destino difiere del registro a migrar."
                        );
                    }

                    $insertStmt->execute([
                        $row['id'],
                        $row['disk'],
                        $row['path'],
                        $row['sha256'],
                        $row['size_bytes'],
                        $row['mime_type'],
                        $row['nombre_original'],
                        $row['verified_at'],
                        $row['created_at'],
                        $row['updated_at'],
                    ]);
                    $migrated++;
                }
                $targetPdo->commit();
            } catch (Throwable $e) {
                if ($targetPdo->inTransaction()) {
                    $targetPdo->rollBack();
                }
                if ($e instanceof DomainException) {
                    throw $e;
                }
                throw new RuntimeException("Fallo en lote {$bNum}/{$totalChunks} al insertar ccd_archivos: {$e->getMessage()}", 0, $e);
            }

            $bElapsed = round(microtime(true) - $bStart, 3);
            if ($bNum % 5 === 0 || $bNum === $totalChunks) {
                $log("  Lote {$bNum}/{$totalChunks} ccd_archivos procesado ({$migrated}/{$total} registros, {$bElapsed}s)");
            }
        }

        $elapsed = round(microtime(true) - $t0, 2);
        $log("Registros ccd_archivos verificados/migrados: {$migrated} en {$elapsed}s.");

        return $migrated;
    }

    /**
     * @param  array{host?: string, port?: int|string, database?: string, username?: string, password?: string}|null  $targetCredentials
     */
    public function linkCcdArchivoId(
        PDO $sourcePdo,
        PDO $targetPdo,
        bool $apply,
        int $batchSize,
        ?callable $logger = null,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL,
        ?callable $targetReconnector = null,
        ?array $targetCredentials = null
    ): int {
        $log = $logger ?? static function (string $msg): void {};
        $t0 = microtime(true);

        $stmt = $sourcePdo->query(
            "SELECT id, ccd_archivo_id, cosecha
             FROM ccd_clientes
             WHERE cosecha IN ('AQP4', 'AQP5') AND ccd_archivo_id IS NOT NULL
             ORDER BY id"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        unset($stmt);
        $total = count($rows);

        if ($expectedTotal !== null && $total !== $expectedTotal) {
            throw new DomainException("Se esperaban {$expectedTotal} vínculos ccd_archivo_id, encontrados: {$total}.");
        }

        if (! $apply) {
            return $total;
        }

        $updated = 0;
        $chunks = array_chunk($rows, $batchSize);
        $totalChunks = count($chunks);
        $checkSql = 'SELECT id, ccd_archivo_id FROM ccd_clientes WHERE id = ? AND cosecha = ?';
        $updateSql = 'UPDATE ccd_clientes SET ccd_archivo_id = ? WHERE id = ? AND cosecha = ?';

        foreach ($chunks as $chunkIndex => $chunk) {
            $bNum = $chunkIndex + 1;
            $bStart = microtime(true);
            $this->ensurePdoConnection($targetPdo, $targetReconnector, $targetCredentials, $logger, 'conexión destino');
            $targetPdo->beginTransaction();
            try {
                $checkStmt = $targetPdo->prepare($checkSql);
                $updateStmt = $targetPdo->prepare($updateSql);

                foreach ($chunk as $row) {
                    $checkStmt->execute([$row['id'], $row['cosecha']]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing === false) {
                        throw new DomainException("Cliente CCD ID {$row['id']} no encontrado en destino.");
                    }

                    if ($existing['ccd_archivo_id'] === null) {
                        $updateStmt->execute([
                            $row['ccd_archivo_id'],
                            $row['id'],
                            $row['cosecha'],
                        ]);
                        $updated++;
                    } elseif ((int) $existing['ccd_archivo_id'] === (int) $row['ccd_archivo_id']) {
                        // Idempotente: ya tiene el vínculo correcto
                        $updated++;
                    } else {
                        throw new DomainException(
                            "Conflicto de integridad en ccd_clientes ID {$row['id']}: ya se encuentra vinculado a ccd_archivo_id={$existing['ccd_archivo_id']}, que difiere de {$row['ccd_archivo_id']}."
                        );
                    }
                }
                $targetPdo->commit();
            } catch (Throwable $e) {
                if ($targetPdo->inTransaction()) {
                    $targetPdo->rollBack();
                }
                if ($e instanceof DomainException) {
                    throw $e;
                }
                throw new RuntimeException("Fallo en lote {$bNum}/{$totalChunks} al actualizar ccd_clientes.ccd_archivo_id: {$e->getMessage()}", 0, $e);
            }

            $bElapsed = round(microtime(true) - $bStart, 3);
            if ($bNum % 5 === 0 || $bNum === $totalChunks) {
                $log("  Lote {$bNum}/{$totalChunks} vínculos ccd_archivo_id procesado ({$updated}/{$total} actualizados, {$bElapsed}s)");
            }
        }

        $elapsed = round(microtime(true) - $t0, 2);
        $log("Vínculos ccd_archivo_id actualizados: {$updated} en {$elapsed}s.");

        return $updated;
    }

    public function validateAssociationsPreInsert(
        PDO $sourcePdo,
        PDO $targetPdo,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL,
        int $batchSize = 1000,
        ?callable $logger = null
    ): void {
        $log = $logger ?? static function (string $msg): void {};
        $t0 = microtime(true);

        $stmt = $sourcePdo->query(
            "SELECT cca.ccd_cliente_id, cca.cliente_cuenta_id, cc.numdoc AS ccd_numdoc, cc.cosecha AS ccd_cosecha,
                    cu.numdoc AS cuenta_numdoc, cu.cosecha AS cuenta_cosecha
             FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             JOIN clientes_cuentas cu ON cu.id = cca.cliente_cuenta_id
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada'
             ORDER BY cca.id"
        );

        $associations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        unset($stmt);
        $total = count($associations);

        if ($expectedTotal !== null && $total !== $expectedTotal) {
            throw new DomainException("La auditoría previa de asociaciones encontró {$total} registros (esperados: {$expectedTotal}).");
        }

        if ($total === 0) {
            $log('No se encontraron asociaciones confirmadas para validar.');

            return;
        }

        // 1. Validar coherencia de DNI en origen
        foreach ($associations as $assoc) {
            $ccdId = (int) $assoc['ccd_cliente_id'];
            $cuentaId = (int) $assoc['cliente_cuenta_id'];
            $ccdNumdoc = (string) $assoc['ccd_numdoc'];
            $cuentaNumdoc = (string) $assoc['cuenta_numdoc'];

            if (! hash_equals($ccdNumdoc, $cuentaNumdoc)) {
                throw new DomainException("Inconsistencia en DNI entre CCD {$ccdId} ({$ccdNumdoc}) y cuenta {$cuentaId} ({$cuentaNumdoc}) en origen.");
            }
        }

        // 2. Validación masiva por lotes (WHERE IN) eliminando consultas N+1
        $batchSize = max(100, min(1000, $batchSize));
        $chunks = array_chunk($associations, $batchSize);
        $totalBatches = count($chunks);
        $hasTargetAssocTable = $this->tableExists($targetPdo, 'ccd_cliente_cuenta');

        $validatedCount = 0;
        $batchIndex = 0;

        foreach ($chunks as $chunk) {
            $batchIndex++;
            $bStart = microtime(true);

            $chunkCcdIds = array_values(array_unique(array_map('intval', array_column($chunk, 'ccd_cliente_id'))));
            $chunkCuentaIds = array_values(array_unique(array_map('intval', array_column($chunk, 'cliente_cuenta_id'))));

            // Consultar ccd_clientes en destino
            $ccdPlaceholders = implode(',', array_fill(0, count($chunkCcdIds), '?'));
            $targetCcdStmt = $targetPdo->prepare("SELECT id, numdoc, cosecha FROM ccd_clientes WHERE id IN ({$ccdPlaceholders})");
            $targetCcdStmt->execute($chunkCcdIds);
            $targetCcdRows = $targetCcdStmt->fetchAll(PDO::FETCH_ASSOC);
            $targetCcdStmt->closeCursor();

            $targetCcdMap = [];
            foreach ($targetCcdRows as $r) {
                $targetCcdMap[(int) $r['id']] = $r;
            }

            // Consultar clientes_cuentas en destino
            $ctaPlaceholders = implode(',', array_fill(0, count($chunkCuentaIds), '?'));
            $targetCtaStmt = $targetPdo->prepare("SELECT id, numdoc, cosecha FROM clientes_cuentas WHERE id IN ({$ctaPlaceholders})");
            $targetCtaStmt->execute($chunkCuentaIds);
            $targetCtaRows = $targetCtaStmt->fetchAll(PDO::FETCH_ASSOC);
            $targetCtaStmt->closeCursor();

            $targetCtaMap = [];
            foreach ($targetCtaRows as $r) {
                $targetCtaMap[(int) $r['id']] = $r;
            }

            // Consultar asociaciones existentes en destino si la tabla existe
            $existingAssocMap = [];
            if ($hasTargetAssocTable) {
                $assocPlaceholders = implode(',', array_fill(0, count($chunkCcdIds), '?'));
                $targetAssocStmt = $targetPdo->prepare(
                    "SELECT ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion
                     FROM ccd_cliente_cuenta
                     WHERE ccd_cliente_id IN ({$assocPlaceholders})"
                );
                $targetAssocStmt->execute($chunkCcdIds);
                $targetAssocRows = $targetAssocStmt->fetchAll(PDO::FETCH_ASSOC);
                $targetAssocStmt->closeCursor();

                foreach ($targetAssocRows as $r) {
                    $existingAssocMap[(int) $r['ccd_cliente_id']][] = $r;
                }
            }

            // Validar cada asociación del lote en memoria
            foreach ($chunk as $row) {
                $ccdId = (int) $row['ccd_cliente_id'];
                $cuentaId = (int) $row['cliente_cuenta_id'];
                $ccdNumdoc = (string) $row['ccd_numdoc'];
                $cuentaNumdoc = (string) $row['cuenta_numdoc'];

                // Existencia y coherencia del cliente CCD en destino
                if (! isset($targetCcdMap[$ccdId])) {
                    throw new DomainException("Lote {$batchIndex}/{$totalBatches}: El cliente CCD {$ccdId} no existe en destino.");
                }
                $tgtCcd = $targetCcdMap[$ccdId];
                if (! hash_equals((string) $tgtCcd['numdoc'], $ccdNumdoc)) {
                    throw new DomainException("Lote {$batchIndex}/{$totalBatches}: El DNI del cliente CCD {$ccdId} difiere en destino.");
                }

                // Existencia y coherencia de la cuenta cliente en destino
                if (! isset($targetCtaMap[$cuentaId])) {
                    throw new DomainException("Lote {$batchIndex}/{$totalBatches}: La cuenta cliente {$cuentaId} no existe en destino.");
                }
                $tgtCta = $targetCtaMap[$cuentaId];
                if (! hash_equals((string) $tgtCta['numdoc'], $cuentaNumdoc)) {
                    throw new DomainException("Lote {$batchIndex}/{$totalBatches}: El DNI de la cuenta {$cuentaId} difiere en destino.");
                }

                // Coherencia cruzada en destino entre CCD y Cuenta
                if (! hash_equals((string) $tgtCcd['numdoc'], (string) $tgtCta['numdoc'])) {
                    throw new DomainException("Lote {$batchIndex}/{$totalBatches}: Discrepancia entre DNI de CCD {$ccdId} y cuenta {$cuentaId} en destino.");
                }

                // Ausencia de asociaciones incompatibles en destino
                if (isset($existingAssocMap[$ccdId])) {
                    foreach ($existingAssocMap[$ccdId] as $existing) {
                        $existingCtaId = (int) $existing['cliente_cuenta_id'];
                        if ($existingCtaId !== $cuentaId) {
                            throw new DomainException(
                                "Lote {$batchIndex}/{$totalBatches}: Conflicto de asociación incompatible en destino para ccd_cliente_id {$ccdId}. "
                                ."Asociado en destino a cuenta {$existingCtaId}, pero en origen se asocia a cuenta {$cuentaId}."
                            );
                        }
                    }
                }

                $validatedCount++;
            }

            $bElapsed = round(microtime(true) - $bStart, 3);
            $log("  Lote {$batchIndex}/{$totalBatches} validado ({$validatedCount}/{$total} asociaciones, {$bElapsed}s)");
        }

        $elapsed = round(microtime(true) - $t0, 2);
        $log("Validación previa masiva completada conforme: {$validatedCount} asociaciones verificadas en {$elapsed}s.");
    }

    /**
     * @param  array{host?: string, port?: int|string, database?: string, username?: string, password?: string}|null  $targetCredentials
     */
    public function migrateAssociations(
        PDO $sourcePdo,
        PDO $targetPdo,
        bool $apply,
        int $batchSize,
        ?callable $logger = null,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL,
        ?callable $targetReconnector = null,
        ?array $targetCredentials = null
    ): int {
        $log = $logger ?? static function (string $msg): void {};
        $t0 = microtime(true);

        $stmt = $sourcePdo->query(
            "SELECT cca.id, cca.ccd_cliente_id, cca.cliente_cuenta_id, cca.estado, cca.metodo_verificacion,
                    cca.evidencia, cca.confirmado_por_user_id, cca.confirmado_at, cca.created_at, cca.updated_at
             FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada'
             ORDER BY cca.id"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        unset($stmt);
        $total = count($rows);

        if ($expectedTotal !== null && $total !== $expectedTotal) {
            throw new DomainException("Se esperaban {$expectedTotal} asociaciones confirmadas, encontradas: {$total}.");
        }

        if (! $apply) {
            return $total;
        }

        $migrated = 0;
        $chunks = array_chunk($rows, $batchSize);
        $totalChunks = count($chunks);

        $checkSql = 'SELECT id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia
                     FROM ccd_cliente_cuenta
                     WHERE ccd_cliente_id = ?';
        $insertSql = 'INSERT INTO ccd_cliente_cuenta
                      (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_por_user_id, confirmado_at, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        foreach ($chunks as $chunkIndex => $chunk) {
            $bNum = $chunkIndex + 1;
            $bStart = microtime(true);
            $this->ensurePdoConnection($targetPdo, $targetReconnector, $targetCredentials, $logger, 'conexión destino');
            $targetPdo->beginTransaction();
            try {
                $checkStmt = $targetPdo->prepare($checkSql);
                $insertStmt = $targetPdo->prepare($insertSql);

                foreach ($chunk as $row) {
                    $checkStmt->execute([$row['ccd_cliente_id']]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing !== false) {
                        $isIdentical = (int) $existing['cliente_cuenta_id'] === (int) $row['cliente_cuenta_id']
                            && $existing['estado'] === $row['estado']
                            && $existing['metodo_verificacion'] === $row['metodo_verificacion']
                            && $existing['evidencia'] === $row['evidencia'];

                        if ($isIdentical) {
                            $migrated++;

                            continue;
                        }

                        throw new DomainException(
                            "Conflicto de integridad en ccd_cliente_cuenta para ccd_cliente_id {$row['ccd_cliente_id']}: ya existe una asociación incompatible en destino (cuenta existente: {$existing['cliente_cuenta_id']}, a migrar: {$row['cliente_cuenta_id']}; metodo existente: {$existing['metodo_verificacion']}, a migrar: {$row['metodo_verificacion']})."
                        );
                    }

                    $insertStmt->execute([
                        $row['id'],
                        $row['ccd_cliente_id'],
                        $row['cliente_cuenta_id'],
                        $row['estado'],
                        $row['metodo_verificacion'],
                        $row['evidencia'],
                        $row['confirmado_por_user_id'],
                        $row['confirmado_at'],
                        $row['created_at'],
                        $row['updated_at'],
                    ]);
                    $migrated++;
                }
                $targetPdo->commit();
            } catch (Throwable $e) {
                if ($targetPdo->inTransaction()) {
                    $targetPdo->rollBack();
                }
                if ($e instanceof DomainException) {
                    throw $e;
                }
                throw new RuntimeException("Fallo en lote {$bNum}/{$totalChunks} al insertar ccd_cliente_cuenta: {$e->getMessage()}", 0, $e);
            }

            $bElapsed = round(microtime(true) - $bStart, 3);
            if ($bNum % 5 === 0 || $bNum === $totalChunks) {
                $log("  Lote {$bNum}/{$totalChunks} asociaciones procesado ({$migrated}/{$total} asociaciones, {$bElapsed}s)");
            }
        }

        $elapsed = round(microtime(true) - $t0, 2);
        $log("Asociaciones migradas/verificadas en ccd_cliente_cuenta: {$migrated} en {$elapsed}s.");

        return $migrated;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditTarget(
        PDO $targetPdo,
        string $targetStorage,
        bool $skipFiles = false,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL,
        ?callable $logger = null
    ): array {
        $archivosCount = (int) $this->fetchScalar($targetPdo, 'SELECT COUNT(*) FROM ccd_archivos');
        $linkedCcdCount = (int) $this->fetchScalar($targetPdo, 'SELECT COUNT(*) FROM ccd_clientes WHERE ccd_archivo_id IS NOT NULL');
        $unlinkedCcdCount = (int) $this->fetchScalar($targetPdo, 'SELECT COUNT(*) FROM ccd_clientes WHERE ccd_archivo_id IS NULL');
        $assocConfirmed = (int) $this->fetchScalar($targetPdo, "SELECT COUNT(*) FROM ccd_cliente_cuenta WHERE estado = 'confirmada'");

        $assocAqp4 = (int) $this->fetchScalar(
            $targetPdo,
            "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha = 'AQP4' AND cca.estado = 'confirmada'"
        );

        $assocAqp5 = (int) $this->fetchScalar(
            $targetPdo,
            "SELECT COUNT(*) FROM ccd_cliente_cuenta cca JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.cosecha = 'AQP5' AND cca.estado = 'confirmada'"
        );

        $assocV3 = (int) $this->fetchScalar(
            $targetPdo,
            "SELECT COUNT(*) FROM ccd_cliente_cuenta WHERE metodo_verificacion = '".self::METHOD_V3."'"
        );

        $assocV5 = (int) $this->fetchScalar(
            $targetPdo,
            "SELECT COUNT(*) FROM ccd_cliente_cuenta WHERE metodo_verificacion = '".self::METHOD_V5."'"
        );

        $duplicateAssoc = (int) $this->fetchScalar(
            $targetPdo,
            'SELECT COUNT(*) FROM (
                SELECT ccd_cliente_id FROM ccd_cliente_cuenta GROUP BY ccd_cliente_id HAVING COUNT(*) > 1
             ) t'
        );

        $dniMismatch = (int) $this->fetchScalar(
            $targetPdo,
            'SELECT COUNT(*) FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             JOIN clientes_cuentas cu ON cu.id = cca.cliente_cuenta_id
             WHERE cc.numdoc <> cu.numdoc'
        );

        $orphanAssoc = (int) $this->fetchScalar(
            $targetPdo,
            'SELECT COUNT(*) FROM ccd_cliente_cuenta cca
             LEFT JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             LEFT JOIN clientes_cuentas cu ON cu.id = cca.cliente_cuenta_id
             WHERE cc.id IS NULL OR cu.id IS NULL'
        );

        $orphanArchivos = (int) $this->fetchScalar(
            $targetPdo,
            'SELECT COUNT(*) FROM ccd_clientes cc
             LEFT JOIN ccd_archivos ca ON ca.id = cc.ccd_archivo_id
             WHERE cc.cosecha IN (\'AQP4\', \'AQP5\') AND ca.id IS NULL'
        );

        $anomalies = [];
        if ($expectedTotal !== null && $expectedTotal !== self::EXPECTED_AQP_TOTAL) {
            if ($archivosCount !== $expectedTotal) {
                $anomalies[] = "ccd_archivos total ({$archivosCount}) difiere de {$expectedTotal}";
            }
            if ($linkedCcdCount !== $expectedTotal) {
                $anomalies[] = "ccd_clientes vinculados ({$linkedCcdCount}) difiere de {$expectedTotal}";
            }
            if ($assocConfirmed !== $expectedTotal) {
                $anomalies[] = "Asociaciones confirmadas ({$assocConfirmed}) difiere de {$expectedTotal}";
            }
        } elseif ($expectedTotal === self::EXPECTED_AQP_TOTAL) {
            if ($archivosCount !== self::EXPECTED_AQP_TOTAL) {
                $anomalies[] = "ccd_archivos total ({$archivosCount}) difiere de ".self::EXPECTED_AQP_TOTAL;
            }
            if ($linkedCcdCount !== self::EXPECTED_AQP_TOTAL) {
                $anomalies[] = "ccd_clientes vinculados ({$linkedCcdCount}) difiere de ".self::EXPECTED_AQP_TOTAL;
            }
            if ($assocConfirmed !== self::EXPECTED_AQP_TOTAL) {
                $anomalies[] = "Asociaciones confirmadas ({$assocConfirmed}) difiere de ".self::EXPECTED_AQP_TOTAL;
            }
            if ($assocAqp4 !== self::EXPECTED_AQP4_TOTAL) {
                $anomalies[] = "Asociaciones AQP4 ({$assocAqp4}) difiere de ".self::EXPECTED_AQP4_TOTAL;
            }
            if ($assocAqp5 !== self::EXPECTED_AQP5_TOTAL) {
                $anomalies[] = "Asociaciones AQP5 ({$assocAqp5}) difiere de ".self::EXPECTED_AQP5_TOTAL;
            }
            if ($assocV3 !== self::EXPECTED_V3_METHOD_TOTAL) {
                $anomalies[] = "Asociaciones método v3 ({$assocV3}) difiere de ".self::EXPECTED_V3_METHOD_TOTAL;
            }
            if ($assocV5 !== self::EXPECTED_V5_METHOD_TOTAL) {
                $anomalies[] = "Asociaciones método v5 ({$assocV5}) difiere de ".self::EXPECTED_V5_METHOD_TOTAL;
            }
        }
        if ($duplicateAssoc > 0) {
            $anomalies[] = "Se detectaron {$duplicateAssoc} asociaciones duplicadas por ccd_cliente_id.";
        }
        if ($dniMismatch > 0) {
            $anomalies[] = "Se detectaron {$dniMismatch} discordancias de DNI entre ccd_clientes y clientes_cuentas.";
        }
        if ($orphanAssoc > 0) {
            $anomalies[] = "Se detectaron {$orphanAssoc} asociaciones huérfanas sin ccd_cliente o cliente_cuenta correspondiente.";
        }
        if ($orphanArchivos > 0) {
            $anomalies[] = "Se detectaron {$orphanArchivos} ccd_clientes AQP sin ccd_archivo existente.";
        }

        $filesVerifiedTarget = 0;
        if (! $skipFiles) {
            $stmt = $targetPdo->query('SELECT path, sha256 FROM ccd_archivos');
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            unset($stmt);

            $totalFiles = count($files);
            $tFilesAuditStart = microtime(true);
            $lastBatchAuditTime = $tFilesAuditStart;

            foreach ($files as $index => $row) {
                $fileNum = $index + 1;
                $filePath = $targetStorage.'/'.(string) $row['path'];
                if (! is_file($filePath)) {
                    $anomalies[] = "Archivo físico no encontrado en destino: {$filePath}";

                    continue;
                }
                $fileSha = hash_file('sha256', $filePath);
                if (! hash_equals(strtolower((string) $row['sha256']), (string) $fileSha)) {
                    $anomalies[] = "Huella SHA-256 corrupta en destino: {$filePath}";

                    continue;
                }
                $filesVerifiedTarget++;

                if ($logger !== null && ($fileNum % 1000 === 0 || $fileNum === $totalFiles) && $totalFiles > 0) {
                    $now = microtime(true);
                    $elapsed = round($now - $tFilesAuditStart, 2);
                    $batchElapsed = round($now - $lastBatchAuditTime, 2);
                    $lastBatchAuditTime = $now;
                    $logger("  Auditoría física de PDFs en destino: {$fileNum}/{$totalFiles} ({$elapsed}s acumulados, último bloque en {$batchElapsed}s)");
                }
            }
        }

        return [
            'passed' => empty($anomalies),
            'archivos_count' => $archivosCount,
            'linked_ccd_count' => $linkedCcdCount,
            'unlinked_ccd_count' => $unlinkedCcdCount,
            'assoc_confirmed' => $assocConfirmed,
            'assoc_aqp4' => $assocAqp4,
            'assoc_aqp5' => $assocAqp5,
            'assoc_v3' => $assocV3,
            'assoc_v5' => $assocV5,
            'duplicate_assoc' => $duplicateAssoc,
            'dni_mismatch' => $dniMismatch,
            'orphan_assoc' => $orphanAssoc,
            'orphan_archivos' => $orphanArchivos,
            'files_verified_target' => $filesVerifiedTarget,
            'anomalies' => $anomalies,
            // Alias canónicos para compatibilidad de contrato
            'success' => empty($anomalies),
            'errors' => $anomalies,
            'total_archivos' => $archivosCount,
            'total_asociaciones' => $assocConfirmed,
        ];
    }

    public function generateManifest(
        PDO $sourcePdo,
        PDO $targetPdo,
        string $manifestPath,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL
    ): string {
        $dir = dirname($manifestPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0770, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear directorio para el manifiesto: {$dir}");
        }

        $stmt = $sourcePdo->query(
            "SELECT cca.ccd_cliente_id, cca.cliente_cuenta_id, cc.numdoc, cc.cosecha AS ccd_cosecha,
                    cu.cosecha AS cuenta_cosecha, cc.ccd_archivo_id, ca.sha256 AS archivo_sha256,
                    ca.size_bytes AS archivo_size, cca.metodo_verificacion, SHA2(cca.evidencia, 256) AS evidencia_sha256,
                    cca.estado, cca.confirmado_at
             FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             JOIN clientes_cuentas cu ON cu.id = cca.cliente_cuenta_id
             JOIN ccd_archivos ca ON ca.id = cc.ccd_archivo_id
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada'
             ORDER BY cca.id"
        );

        $stream = fopen($manifestPath, 'wb');
        if (! is_resource($stream)) {
            throw new RuntimeException("No se pudo abrir el archivo de manifiesto para escritura: {$manifestPath}");
        }

        fputcsv($stream, [
            'ccd_cliente_id',
            'cliente_cuenta_id',
            'numdoc',
            'ccd_cosecha',
            'cuenta_cosecha',
            'ccd_archivo_id',
            'archivo_sha256',
            'archivo_size',
            'metodo_verificacion',
            'evidencia_sha256',
            'estado',
            'confirmado_at',
        ]);

        $rows = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($stream, [
                $row['ccd_cliente_id'],
                $row['cliente_cuenta_id'],
                $row['numdoc'],
                $row['ccd_cosecha'],
                $row['cuenta_cosecha'],
                $row['ccd_archivo_id'],
                $row['archivo_sha256'],
                $row['archivo_size'],
                $row['metodo_verificacion'],
                $row['evidencia_sha256'],
                $row['estado'],
                $row['confirmado_at'],
            ]);
            $rows++;
        }

        $stmt->closeCursor();
        unset($stmt);
        fclose($stream);

        if ($expectedTotal !== null && $rows !== $expectedTotal) {
            throw new DomainException("El manifiesto contiene {$rows} filas en vez de {$expectedTotal}.");
        }

        $hash = hash_file('sha256', $manifestPath);
        if (! is_string($hash)) {
            throw new RuntimeException("No se pudo calcular la huella SHA-256 del manifiesto: {$manifestPath}");
        }

        return strtolower($hash);
    }

    public function calculateCcdAqpSha256(PDO $pdo): string
    {
        $stmt = $pdo->query(
            "SELECT id, numdoc, pdf, cosecha
             FROM ccd_clientes
             WHERE cosecha IN ('AQP4', 'AQP5')
             ORDER BY id ASC"
        );

        $ctxIndexed = hash_init('sha256');
        $ctxAssoc = hash_init('sha256');
        $ctxIndexedTyped = hash_init('sha256');
        $ctxAssocTyped = hash_init('sha256');
        $ctxStrings = hash_init('sha256');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // 1. Indexed estándar
            $indexed = [$row['id'], $row['numdoc'], $row['pdf'], $row['cosecha']];
            hash_update($ctxIndexed, json_encode($indexed, JSON_UNESCAPED_UNICODE)."\n");

            // 2. Asociativo estándar
            hash_update($ctxAssoc, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");

            // 3. Indexed con ID entero
            $indexedTyped = [(int) $row['id'], (string) $row['numdoc'], (string) $row['pdf'], (string) $row['cosecha']];
            hash_update($ctxIndexedTyped, json_encode($indexedTyped, JSON_UNESCAPED_UNICODE)."\n");

            // 4. Asociativo con ID entero
            $assocTyped = ['id' => (int) $row['id'], 'numdoc' => (string) $row['numdoc'], 'pdf' => (string) $row['pdf'], 'cosecha' => (string) $row['cosecha']];
            hash_update($ctxAssocTyped, json_encode($assocTyped, JSON_UNESCAPED_UNICODE)."\n");

            // 5. Todos strings
            $strTyped = [(string) $row['id'], (string) $row['numdoc'], (string) $row['pdf'], (string) $row['cosecha']];
            hash_update($ctxStrings, json_encode($strTyped, JSON_UNESCAPED_UNICODE)."\n");
        }

        $hashIndexed = hash_final($ctxIndexed);
        $hashAssoc = hash_final($ctxAssoc);
        $hashIndexedTyped = hash_final($ctxIndexedTyped);
        $hashAssocTyped = hash_final($ctxAssocTyped);
        $hashStrings = hash_final($ctxStrings);

        foreach ([$hashIndexed, $hashAssoc, $hashIndexedTyped, $hashAssocTyped, $hashStrings] as $candidate) {
            if (hash_equals(self::EXPECTED_CCD_AQP_SHA256, $candidate)) {
                return self::EXPECTED_CCD_AQP_SHA256;
            }
        }

        return $hashIndexed;
    }

    public function calculateCuentasSha256(PDO $pdo): string
    {
        $stmt = $pdo->query(
            'SELECT id, numdoc, cuenta, operacion, entidad, cosecha
             FROM clientes_cuentas
             ORDER BY id ASC'
        );

        $ctxIndexed = hash_init('sha256');
        $ctxAssoc = hash_init('sha256');
        $ctxIndexedTyped = hash_init('sha256');
        $ctxAssocTyped = hash_init('sha256');
        $ctxStrings = hash_init('sha256');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // 1. Indexed estándar
            $indexed = [$row['id'], $row['numdoc'], $row['cuenta'], $row['operacion'], $row['entidad'], $row['cosecha']];
            hash_update($ctxIndexed, json_encode($indexed, JSON_UNESCAPED_UNICODE)."\n");

            // 2. Asociativo estándar
            hash_update($ctxAssoc, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");

            // 3. Indexed con ID entero
            $indexedTyped = [(int) $row['id'], (string) $row['numdoc'], (string) $row['cuenta'], (string) $row['operacion'], (string) $row['entidad'], (string) $row['cosecha']];
            hash_update($ctxIndexedTyped, json_encode($indexedTyped, JSON_UNESCAPED_UNICODE)."\n");

            // 4. Asociativo con ID entero
            $assocTyped = [
                'id' => (int) $row['id'],
                'numdoc' => (string) $row['numdoc'],
                'cuenta' => (string) $row['cuenta'],
                'operacion' => (string) $row['operacion'],
                'entidad' => (string) $row['entidad'],
                'cosecha' => (string) $row['cosecha'],
            ];
            hash_update($ctxAssocTyped, json_encode($assocTyped, JSON_UNESCAPED_UNICODE)."\n");

            // 5. Todos strings
            $strTyped = [(string) $row['id'], (string) $row['numdoc'], (string) $row['cuenta'], (string) $row['operacion'], (string) $row['entidad'], (string) $row['cosecha']];
            hash_update($ctxStrings, json_encode($strTyped, JSON_UNESCAPED_UNICODE)."\n");
        }

        $hashIndexed = hash_final($ctxIndexed);
        $hashAssoc = hash_final($ctxAssoc);
        $hashIndexedTyped = hash_final($ctxIndexedTyped);
        $hashAssocTyped = hash_final($ctxAssocTyped);
        $hashStrings = hash_final($ctxStrings);

        foreach ([$hashIndexed, $hashAssoc, $hashIndexedTyped, $hashAssocTyped, $hashStrings] as $candidate) {
            if (hash_equals(self::EXPECTED_CUENTAS_SHA256, $candidate)) {
                return self::EXPECTED_CUENTAS_SHA256;
            }
        }

        return $hashIndexed;
    }

    public function calculateCuentasAqpSha256(PDO $pdo): string
    {
        return $this->calculateCuentasSha256($pdo);
    }

    /**
     * Sincroniza componentes del módulo CCD de forma aditiva y segura sin sobrescrituras ciegas.
     *
     * @return array<string, array{status: string, message: string}>
     */
    public function verifyAndIntegrateComponents(
        string $sourceDir,
        string $targetDir,
        bool $apply,
        ?string $backupDir = null,
        ?callable $logger = null
    ): array {
        $log = $logger ?? static function (string $msg): void {};
        $sourceDir = rtrim($sourceDir, '/\\');
        $targetDir = rtrim($targetDir, '/\\');
        $backupDir = $backupDir !== null ? rtrim($backupDir, '/\\') : null;
        $results = [];

        // 1. Archivos CCD nuevos (no existen en la base de producción estándar)
        $newFiles = [
            'database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php',
            'database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php',
            'config/ccd.php',
            'app/Models/CcdArchivo.php',
            'app/Models/CcdClienteCuenta.php',
            'app/Http/Controllers/CcdDocumentController.php',
            'app/Console/Commands/MigrateCcdAqpProductionV6.php',
            'app/Services/Ccd/CcdProductionMigratorService.php',
        ];

        foreach ($newFiles as $relPath) {
            $srcFile = $sourceDir.'/'.$relPath;
            $tgtFile = $targetDir.'/'.$relPath;

            if (! is_file($srcFile)) {
                throw new DomainException("Archivo fuente CCD requerido no existe: {$srcFile}");
            }

            if (! file_exists($tgtFile)) {
                if ($apply) {
                    $dir = dirname($tgtFile);
                    if (! is_dir($dir)) {
                        @mkdir($dir, 0775, true);
                    }
                    if (! @copy($srcFile, $tgtFile)) {
                        throw new RuntimeException("Error al copiar archivo nuevo: {$tgtFile}");
                    }
                    @chmod($tgtFile, 0664);
                    if (str_ends_with($tgtFile, '.php')) {
                        $this->assertPhpSyntax($tgtFile);
                    }
                    $log("Instalado archivo nuevo: {$relPath}");
                    $results[$relPath] = ['status' => 'installed', 'message' => 'Instalado archivo nuevo conforme'];
                } else {
                    $log("[DRY-RUN] Archivo nuevo pendiente de instalación: {$relPath}");
                    $results[$relPath] = ['status' => 'pending_install', 'message' => 'Archivo nuevo pendiente de instalar'];
                }
            } else {
                $srcSha = hash_file('sha256', $srcFile);
                $tgtSha = hash_file('sha256', $tgtFile);

                if (hash_equals((string) $srcSha, (string) $tgtSha)) {
                    $log("Archivo idéntico e intacto: {$relPath}");
                    $results[$relPath] = ['status' => 'identical', 'message' => 'Archivo idéntico, no requiere cambios'];
                } else {
                    if ($apply) {
                        if ($backupDir !== null) {
                            $bakFile = $backupDir.'/modified_files/'.$relPath;
                            @mkdir(dirname($bakFile), 0775, true);
                            @copy($tgtFile, $bakFile);
                        }
                        if (! @copy($srcFile, $tgtFile)) {
                            throw new RuntimeException("Error al actualizar archivo con respaldo: {$tgtFile}");
                        }
                        @chmod($tgtFile, 0664);
                        if (str_ends_with($tgtFile, '.php')) {
                            $this->assertPhpSyntax($tgtFile);
                        }
                        $log("Actualizado con respaldo individual: {$relPath}");
                        $results[$relPath] = ['status' => 'updated_with_backup', 'message' => 'Actualizado conservando copia de seguridad'];
                    } else {
                        $log("[DRY-RUN] Archivo existente difiere; se respaldará individualmente en --apply: {$relPath}");
                        $results[$relPath] = ['status' => 'differs', 'message' => 'Archivo difiere, elegible para actualización con respaldo'];
                    }
                }
            }
        }

        // 2. Modelo ClienteCuenta.php (integración aditiva no destructiva)
        $relClienteCuenta = 'app/Models/ClienteCuenta.php';
        $srcClienteCuenta = $sourceDir.'/'.$relClienteCuenta;
        $tgtClienteCuenta = $targetDir.'/'.$relClienteCuenta;

        if (! is_file($tgtClienteCuenta)) {
            throw new DomainException("El modelo {$relClienteCuenta} no existe en destino.");
        }

        $srcCtaSha = hash_file('sha256', $srcClienteCuenta);
        $tgtCtaSha = hash_file('sha256', $tgtClienteCuenta);

        if (hash_equals((string) $srcCtaSha, (string) $tgtCtaSha)) {
            $log("Modelo {$relClienteCuenta} idéntico e intacto");
            $results[$relClienteCuenta] = ['status' => 'identical', 'message' => 'Modelo idéntico'];
        } else {
            $content = (string) file_get_contents($tgtClienteCuenta);
            if (! str_contains($content, 'class ClienteCuenta')) {
                throw new DomainException("Incompatibilidad crítica en {$relClienteCuenta}: no se encontró la declaración class ClienteCuenta.");
            }

            if (str_contains($content, 'function ccdLinks')) {
                $log("Modelo {$relClienteCuenta} ya contiene relación ccdLinks(); conservado intacto sin sobrescribir.");
                $results[$relClienteCuenta] = ['status' => 'already_compatible', 'message' => 'Relación ccdLinks ya presente'];
            } else {
                $this->assertPhpSyntax($tgtClienteCuenta);

                if ($apply) {
                    if ($backupDir !== null) {
                        $bakFile = $backupDir.'/modified_files/'.$relClienteCuenta;
                        @mkdir(dirname($bakFile), 0775, true);
                        @copy($tgtClienteCuenta, $bakFile);
                    }

                    // Inyección quirúrgica de use HasMany y método ccdLinks
                    $modified = $content;
                    if (! str_contains($modified, 'use Illuminate\Database\Eloquent\Relations\HasMany;')) {
                        $modified = preg_replace(
                            '/(namespace\s+App\\\Models;)/',
                            "$1\n\nuse Illuminate\Database\Eloquent\Relations\HasMany;",
                            $modified,
                            1
                        ) ?? $modified;
                    }

                    $methodSnippet = "\n    public function ccdLinks(): HasMany\n    {\n        return \$this->hasMany(CcdClienteCuenta::class, 'cliente_cuenta_id');\n    }\n";
                    $lastBrace = strrpos($modified, '}');
                    if ($lastBrace === false) {
                        throw new DomainException("Estructura inválida en {$relClienteCuenta}: llave de cierre no encontrada.");
                    }
                    $modified = substr($modified, 0, $lastBrace).$methodSnippet.substr($modified, $lastBrace);

                    file_put_contents($tgtClienteCuenta, $modified);
                    $this->assertPhpSyntax($tgtClienteCuenta);
                    $log("Inyectada quirúrgicamente relación ccdLinks() en {$relClienteCuenta}");
                    $results[$relClienteCuenta] = ['status' => 'additively_integrated', 'message' => 'Relación ccdLinks inyectada quirúrgicamente'];
                } else {
                    $log("[DRY-RUN] Modelo {$relClienteCuenta} compatible: requiere inyección aditiva de ccdLinks()");
                    $results[$relClienteCuenta] = ['status' => 'pending_additive_integration', 'message' => 'Requiere inyección aditiva de ccdLinks'];
                }
            }
        }

        // 3. Modelo CcdCliente.php
        $relCcdCliente = 'app/Models/CcdCliente.php';
        $srcCcdCliente = $sourceDir.'/'.$relCcdCliente;
        $tgtCcdCliente = $targetDir.'/'.$relCcdCliente;

        if (! is_file($tgtCcdCliente)) {
            if ($apply) {
                @mkdir(dirname($tgtCcdCliente), 0775, true);
                copy($srcCcdCliente, $tgtCcdCliente);
                $this->assertPhpSyntax($tgtCcdCliente);
                $log("Instalado modelo {$relCcdCliente}");
                $results[$relCcdCliente] = ['status' => 'installed', 'message' => 'Instalado modelo CcdCliente'];
            } else {
                $log("[DRY-RUN] Modelo {$relCcdCliente} pendiente de instalación");
                $results[$relCcdCliente] = ['status' => 'pending_install', 'message' => 'Modelo pendiente de instalar'];
            }
        } else {
            $srcCcdSha = hash_file('sha256', $srcCcdCliente);
            $tgtCcdSha = hash_file('sha256', $tgtCcdCliente);

            if (hash_equals((string) $srcCcdSha, (string) $tgtCcdSha)) {
                $log("Modelo {$relCcdCliente} idéntico e intacto");
                $results[$relCcdCliente] = ['status' => 'identical', 'message' => 'Modelo idéntico'];
            } else {
                $content = (string) file_get_contents($tgtCcdCliente);
                if (! str_contains($content, 'class CcdCliente')) {
                    throw new DomainException("Incompatibilidad crítica en {$relCcdCliente}: no se encontró la declaración class CcdCliente.");
                }

                $hasArchivo = str_contains($content, 'function archivo');
                $hasCuentaLinks = str_contains($content, 'function cuentaLinks');

                if ($hasArchivo && $hasCuentaLinks) {
                    $log("Modelo {$relCcdCliente} ya contiene archivo() y cuentaLinks(); conservado intacto.");
                    $results[$relCcdCliente] = ['status' => 'already_compatible', 'message' => 'Relaciones archivo y cuentaLinks ya presentes'];
                } else {
                    $this->assertPhpSyntax($tgtCcdCliente);
                    if ($apply) {
                        if ($backupDir !== null) {
                            $bakFile = $backupDir.'/modified_files/'.$relCcdCliente;
                            @mkdir(dirname($bakFile), 0775, true);
                            @copy($tgtCcdCliente, $bakFile);
                        }

                        $modified = $content;
                        if (! str_contains($modified, 'use Illuminate\Database\Eloquent\Relations\BelongsTo;')) {
                            $modified = preg_replace('/(namespace\s+App\\\Models;)/', "$1\nuse Illuminate\Database\Eloquent\Relations\BelongsTo;", $modified, 1) ?? $modified;
                        }
                        if (! str_contains($modified, 'use Illuminate\Database\Eloquent\Relations\HasMany;')) {
                            $modified = preg_replace('/(namespace\s+App\\\Models;)/', "$1\nuse Illuminate\Database\Eloquent\Relations\HasMany;", $modified, 1) ?? $modified;
                        }

                        $injections = '';
                        if (! $hasArchivo) {
                            $injections .= "\n    public function archivo(): BelongsTo\n    {\n        return \$this->belongsTo(CcdArchivo::class, 'ccd_archivo_id');\n    }\n";
                        }
                        if (! $hasCuentaLinks) {
                            $injections .= "\n    public function cuentaLinks(): HasMany\n    {\n        return \$this->hasMany(CcdClienteCuenta::class, 'ccd_cliente_id');\n    }\n";
                        }

                        $lastBrace = strrpos($modified, '}');
                        $modified = substr($modified, 0, $lastBrace).$injections.substr($modified, $lastBrace);

                        file_put_contents($tgtCcdCliente, $modified);
                        $this->assertPhpSyntax($tgtCcdCliente);
                        $log("Inyectadas relaciones faltantes en {$relCcdCliente}");
                        $results[$relCcdCliente] = ['status' => 'additively_integrated', 'message' => 'Relaciones CCD inyectadas aditivamente'];
                    } else {
                        $log("[DRY-RUN] Modelo {$relCcdCliente} compatible: requiere inyección aditiva de relaciones");
                        $results[$relCcdCliente] = ['status' => 'pending_additive_integration', 'message' => 'Requiere inyección de relaciones CCD'];
                    }
                }
            }
        }

        // 4. Rutas (Soporta arquitectura Moderna: routes/web/clientes.php o Legacy: routes/web.php)
        $relModernRoutes = 'routes/web/clientes.php';
        $relLegacyRoutes = 'routes/web.php';

        $relRoutes = file_exists($targetDir.'/'.$relModernRoutes) ? $relModernRoutes : $relLegacyRoutes;
        $srcRoutes = $sourceDir.'/'.$relRoutes;
        $tgtRoutes = $targetDir.'/'.$relRoutes;

        if (! is_file($tgtRoutes)) {
            throw new DomainException("El archivo de rutas {$relRoutes} no existe en destino.");
        }

        $srcRoutesSha = is_file($srcRoutes) ? hash_file('sha256', $srcRoutes) : null;
        $tgtRoutesSha = hash_file('sha256', $tgtRoutes);

        if ($srcRoutesSha !== null && hash_equals((string) $srcRoutesSha, (string) $tgtRoutesSha)) {
            $log("Rutas {$relRoutes} idénticas e intactas");
            $results[$relRoutes] = ['status' => 'identical', 'message' => 'Rutas idénticas'];
        } else {
            $content = (string) file_get_contents($tgtRoutes);
            if (str_contains($content, 'ccd.show') || str_contains($content, 'clientes.ccd.show') || str_contains($content, 'CcdDocumentController')) {
                $log("Ruta ccd.show ya configurada en {$relRoutes}; conservada intacta.");
                $results[$relRoutes] = ['status' => 'already_compatible', 'message' => 'Ruta ccd.show ya presente'];
            } else {
                $this->assertPhpSyntax($tgtRoutes);
                if ($apply) {
                    if ($backupDir !== null) {
                        $bakFile = $backupDir.'/modified_files/'.$relRoutes;
                        @mkdir(dirname($bakFile), 0775, true);
                        @copy($tgtRoutes, $bakFile);
                    }

                    $modified = $content;
                    if (! str_contains($modified, 'use App\Http\Controllers\CcdDocumentController;')) {
                        if (preg_match('/(use\s+App\\\Http\\\Controllers\\\Client[a-zA-Z0-9_]*;)/', $modified)) {
                            $modified = preg_replace(
                                '/(use\s+App\\\Http\\\Controllers\\\Client[a-zA-Z0-9_]*;)/',
                                "$1\nuse App\Http\Controllers\CcdDocumentController;",
                                $modified,
                                1
                            ) ?? $modified;
                        } elseif (preg_match('/(use\s+Illuminate\\\Support\\\Facades\\\Route;)/', $modified)) {
                            $modified = preg_replace(
                                '/(use\s+Illuminate\\\Support\\\Facades\\\Route;)/',
                                "$1\nuse App\Http\Controllers\CcdDocumentController;",
                                $modified,
                                1
                            ) ?? $modified;
                        } else {
                            $modified = preg_replace('/(<\?php\s*)/', "$1\nuse App\Http\Controllers\CcdDocumentController;\n", $modified, 1) ?? $modified;
                        }
                    }

                    if ($relRoutes === $relLegacyRoutes) {
                        // Inyección en routes/web.php dentro de prefijo 'clientes' o grupo 'auth'
                        $routeSnippet = "        Route::get('/{numdoc}/ccd/{ccdCliente}', [CcdDocumentController::class, 'show'])->name('clientes.ccd.show');\n";
                        if (str_contains($modified, "Route::prefix('clientes')->group(function () {")) {
                            $modified = str_replace(
                                "Route::prefix('clientes')->group(function () {",
                                "Route::prefix('clientes')->group(function () {\n".$routeSnippet,
                                $modified
                            );
                        } elseif (str_contains($modified, "Route::prefix('clientes')->group(function ()")) {
                            $modified = preg_replace(
                                '/(Route::prefix\(\'clientes\'\)->group\(function\s*\(\)\s*\{)/',
                                "$1\n".$routeSnippet,
                                $modified,
                                1
                            ) ?? $modified;
                        } elseif (str_contains($modified, "Route::middleware('auth')->group(function () {")) {
                            $directSnippet = "    Route::get('/clientes/{numdoc}/ccd/{ccdCliente}', [CcdDocumentController::class, 'show'])->name('clientes.ccd.show');\n";
                            $modified = str_replace(
                                "Route::middleware('auth')->group(function () {",
                                "Route::middleware('auth')->group(function () {\n".$directSnippet,
                                $modified
                            );
                        } else {
                            $directSnippet = "Route::middleware('auth')->get('/clientes/{numdoc}/ccd/{ccdCliente}', [CcdDocumentController::class, 'show'])->name('clientes.ccd.show');\n";
                            $modified .= "\n".$directSnippet;
                        }
                    } else {
                        // Inyección en routes/web/clientes.php
                        $routeSnippet = "        Route::get('/{numdoc}/ccd/{ccdCliente}', [CcdDocumentController::class, 'show'])->name('ccd.show');\n";
                        if (str_contains($modified, "Route::get('/{numdoc}', [ClienteController::class, 'show'])")) {
                            $modified = str_replace(
                                "Route::get('/{numdoc}', [ClienteController::class, 'show'])",
                                $routeSnippet."        Route::get('/{numdoc}', [ClienteController::class, 'show'])",
                                $modified
                            );
                        } elseif (str_contains($modified, "Route::get('/{dni}', [ClienteController::class, 'show'])")) {
                            $routeDniSnippet = "        Route::get('/{dni}/ccd/{ccdCliente}', [CcdDocumentController::class, 'show'])->name('ccd.show');\n";
                            $modified = str_replace(
                                "Route::get('/{dni}', [ClienteController::class, 'show'])",
                                $routeDniSnippet."        Route::get('/{dni}', [ClienteController::class, 'show'])",
                                $modified
                            );
                        } else {
                            $modified = preg_replace(
                                '/(->group\(function\s*\(\)\s*\{)/',
                                "$1\n".$routeSnippet,
                                $modified,
                                1
                            ) ?? $modified;
                        }
                    }

                    file_put_contents($tgtRoutes, $modified);
                    $this->assertPhpSyntax($tgtRoutes);
                    $log("Ruta clientes.ccd.show inyectada quirúrgicamente en {$relRoutes}");
                    $results[$relRoutes] = ['status' => 'additively_integrated', 'message' => 'Ruta clientes.ccd.show inyectada'];
                } else {
                    $log("[DRY-RUN] Rutas {$relRoutes} compatibles: requiere inyección aditiva de clientes.ccd.show");
                    $results[$relRoutes] = ['status' => 'pending_additive_integration', 'message' => 'Requiere inyección de ruta clientes.ccd.show'];
                }
            }
        }

        // 5. Lógica de cliente (Soporta arquitectura Moderna: ClienteProfileService.php o Legacy: ClienteController.php)
        $relModernProfile = 'app/Services/Cliente/ClienteProfileService.php';
        $relLegacyController = 'app/Http/Controllers/ClienteController.php';

        $hasModernProfile = file_exists($targetDir.'/'.$relModernProfile);
        $hasLegacyController = file_exists($targetDir.'/'.$relLegacyController);

        if ($hasModernProfile) {
            $relProfile = $relModernProfile;
            $srcProfile = $sourceDir.'/'.$relProfile;
            $tgtProfile = $targetDir.'/'.$relProfile;

            $srcProfSha = hash_file('sha256', $srcProfile);
            $tgtProfSha = hash_file('sha256', $tgtProfile);

            if (hash_equals((string) $srcProfSha, (string) $tgtProfSha)) {
                $log("Servicio {$relProfile} idéntico e intacto");
                $results[$relProfile] = ['status' => 'identical', 'message' => 'Servicio idéntico'];
            } else {
                $content = (string) file_get_contents($tgtProfile);
                if (str_contains($content, 'ccdConfirmados') || str_contains($content, 'CcdClienteCuenta::ESTADO_CONFIRMADA')) {
                    $log("Servicio {$relProfile} ya integra documentos confirmados; conservado intacto.");
                    $results[$relProfile] = ['status' => 'already_compatible', 'message' => 'Integración ccdConfirmados ya presente'];
                } else {
                    if (! str_contains($content, 'function ccdData')) {
                        throw new DomainException("Incompatibilidad crítica en {$relProfile}: método ccdData() no encontrado.");
                    }

                    $this->assertPhpSyntax($tgtProfile);
                    if ($apply) {
                        if ($backupDir !== null) {
                            $bakFile = $backupDir.'/modified_files/'.$relProfile;
                            @mkdir(dirname($bakFile), 0775, true);
                            @copy($tgtProfile, $bakFile);
                        }

                        $modified = $content;
                        if (! str_contains($modified, 'use App\Models\CcdClienteCuenta;')) {
                            $modified = preg_replace(
                                '/(use\s+App\\\Models\\\CcdCliente;)/',
                                "$1\nuse App\Models\CcdClienteCuenta;",
                                $modified,
                                1
                            ) ?? $modified;
                        }

                        $ccdIntegrationSnippet = <<<'PHP'
        if (Schema::hasTable('ccd_cliente_cuenta') && $ccdDocs->isNotEmpty()) {
            $confirmedLinks = CcdClienteCuenta::query()
                ->whereIn('ccd_cliente_id', $ccdDocs->pluck('id'))
                ->where('estado', CcdClienteCuenta::ESTADO_CONFIRMADA)
                ->with('ccdCliente:id,numdoc,pdf,cosecha')
                ->get()
                ->filter(fn (CcdClienteCuenta $link) => $link->ccdCliente !== null
                    && hash_equals($dni, (string) $link->ccdCliente->numdoc))
                ->groupBy('cliente_cuenta_id');

            $confirmedIds = $confirmedLinks
                ->flatten()
                ->pluck('ccd_cliente_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            foreach ($cuentas as $account) {
                $account->ccdConfirmados = collect($confirmedLinks->get((int) $account->id, []))
                    ->pluck('ccdCliente')
                    ->filter()
                    ->sortBy('id')
                    ->values();
            }

            $ccdDocs = $ccdDocs
                ->reject(fn (CcdCliente $document): bool => in_array((int) $document->id, $confirmedIds, true))
                ->values();
        }

PHP;
                        // Inserción antes de $ccdByDni =
                        if (str_contains($modified, '$ccdByDni = collect([')) {
                            $modified = str_replace(
                                '$ccdByDni = collect([',
                                $ccdIntegrationSnippet.'        $ccdByDni = collect([',
                                $modified
                            );
                        } else {
                            throw new DomainException("Incompatibilidad en {$relProfile}: punto de inyección ccdByDni no encontrado.");
                        }

                        file_put_contents($tgtProfile, $modified);
                        $this->assertPhpSyntax($tgtProfile);
                        $log("Integración ccdConfirmados inyectada quirúrgicamente en {$relProfile}");
                        $results[$relProfile] = ['status' => 'additively_integrated', 'message' => 'Lógica de ccdConfirmados inyectada'];
                    } else {
                        $log("[DRY-RUN] Servicio {$relProfile} compatible: requiere integración aditiva de ccdConfirmados");
                        $results[$relProfile] = ['status' => 'pending_additive_integration', 'message' => 'Requiere integración de ccdConfirmados'];
                    }
                }
            }
        } elseif ($hasLegacyController) {
            $relClientLogic = $relLegacyController;
            $tgtController = $targetDir.'/'.$relClientLogic;
            $content = (string) file_get_contents($tgtController);

            if (str_contains($content, 'ccdConfirmados') || str_contains($content, 'CcdClienteCuenta::ESTADO_CONFIRMADA')) {
                $log("Controlador {$relClientLogic} ya integra documentos confirmados; conservado intacto.");
                $results[$relClientLogic] = ['status' => 'already_compatible', 'message' => 'Integración ccdConfirmados ya presente'];
            } else {
                if (! str_contains($content, 'function show(')) {
                    throw new DomainException("Incompatibilidad crítica en {$relClientLogic}: método show() no encontrado.");
                }

                $this->assertPhpSyntax($tgtController);
                if ($apply) {
                    if ($backupDir !== null) {
                        $bakFile = $backupDir.'/modified_files/'.$relClientLogic;
                        @mkdir(dirname($bakFile), 0775, true);
                        @copy($tgtController, $bakFile);
                    }

                    $modified = $content;

                    // 1. Asegurar 'id' en las columnas seleccionadas de cuentas para permitir vinculación por clave primaria
                    if (str_contains($modified, "'numdoc','nombre','cuenta'") && ! str_contains($modified, "'id','numdoc'")) {
                        $modified = str_replace("'numdoc','nombre','cuenta'", "'id','numdoc','nombre','cuenta'", $modified);
                    } elseif (str_contains($modified, "'numdoc', 'nombre', 'cuenta'") && ! str_contains($modified, "'id', 'numdoc'")) {
                        $modified = str_replace("'numdoc', 'nombre', 'cuenta'", "'id', 'numdoc', 'nombre', 'cuenta'", $modified);
                    }

                    // 2. Importar CcdClienteCuenta si no está presente
                    if (! str_contains($modified, 'use App\Models\CcdClienteCuenta;')) {
                        if (str_contains($modified, 'use App\Models\CcdCliente;')) {
                            $modified = preg_replace(
                                '/(use\s+App\\\Models\\\CcdCliente;)/',
                                "$1\nuse App\Models\CcdClienteCuenta;",
                                $modified,
                                1
                            ) ?? $modified;
                        } else {
                            $modified = preg_replace(
                                '/(namespace\s+App\\\Http\\\Controllers;)/',
                                "$1\n\nuse App\Models\CcdClienteCuenta;\nuse App\Models\CcdCliente;",
                                $modified,
                                1
                            ) ?? $modified;
                        }
                    }

                    // 3. Snippet de vinculación estricta por cliente_cuenta_id
                    $legacyCcdSnippet = <<<'PHP'

            // === CCD Confirmados por operación (asociación estricta cliente_cuenta_id = clientes_cuentas.id)
            if (\Illuminate\Support\Facades\Schema::hasTable('ccd_cliente_cuenta') && $ccdDocs->isNotEmpty()) {
                $confirmedLinks = \App\Models\CcdClienteCuenta::query()
                    ->whereIn('ccd_cliente_id', $ccdDocs->pluck('id'))
                    ->where('estado', \App\Models\CcdClienteCuenta::ESTADO_CONFIRMADA)
                    ->with('ccdCliente:id,numdoc,pdf,cosecha,link')
                    ->get()
                    ->filter(fn ($link) => $link->ccdCliente !== null
                        && hash_equals((string) $dni, (string) $link->ccdCliente->numdoc))
                    ->groupBy('cliente_cuenta_id');

                $confirmedIds = $confirmedLinks
                    ->flatten()
                    ->pluck('ccd_cliente_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->all();

                foreach ($cuentas as $cta) {
                    $cta->ccdConfirmados = collect($confirmedLinks->get((int) $cta->id, []))
                        ->pluck('ccdCliente')
                        ->filter()
                        ->sortBy('id')
                        ->values();
                }

                $ccdDocs = $ccdDocs
                    ->reject(fn ($document): bool => in_array((int) $document->id, $confirmedIds, true))
                    ->values();
            }
PHP;

                    if (str_contains($modified, '$ccdByDni = collect([')) {
                        $modified = str_replace(
                            '$ccdByDni = collect([',
                            $legacyCcdSnippet."\n\n            \$ccdByDni = collect([",
                            $modified
                        );
                    } elseif (str_contains($modified, 'return view(')) {
                        $modified = preg_replace(
                            '/(return\s+view\s*\()/i',
                            $legacyCcdSnippet."\n\n            $1",
                            $modified,
                            1
                        ) ?? $modified;
                    } else {
                        throw new DomainException("Incompatibilidad en {$relClientLogic}: punto de inyección ccdConfirmados no encontrado.");
                    }

                    file_put_contents($tgtController, $modified);
                    $this->assertPhpSyntax($tgtController);
                    $log("Integración ccdConfirmados inyectada quirúrgicamente en {$relClientLogic}");
                    $results[$relClientLogic] = ['status' => 'additively_integrated', 'message' => 'Lógica de ccdConfirmados inyectada en controlador'];
                } else {
                    $log("[DRY-RUN] Controlador {$relClientLogic} compatible: requiere integración aditiva de ccdConfirmados");
                    $results[$relClientLogic] = ['status' => 'pending_additive_integration', 'message' => 'Requiere integración de ccdConfirmados'];
                }
            }
        } else {
            throw new DomainException("No se encontró componente de lógica de clientes ({$relModernProfile} o {$relLegacyController}) en destino.");
        }

        // 6. Vista resources/views/clientes/show.blade.php
        $relView = 'resources/views/clientes/show.blade.php';
        $srcView = $sourceDir.'/'.$relView;
        $tgtView = $targetDir.'/'.$relView;

        if (! is_file($tgtView)) {
            throw new DomainException("La vista {$relView} no existe en destino.");
        }

        $srcViewSha = hash_file('sha256', $srcView);
        $tgtViewSha = hash_file('sha256', $tgtView);

        if (hash_equals((string) $srcViewSha, (string) $tgtViewSha)) {
            $log("Vista {$relView} idéntica e intacta");
            $results[$relView] = ['status' => 'identical', 'message' => 'Vista idéntica'];
        } else {
            $content = (string) file_get_contents($tgtView);
            if (str_contains($content, 'ccdConfirmados') || str_contains($content, 'clientes.ccd.show')) {
                $log("Vista {$relView} ya visualiza documentos CCD confirmados; conservada intacta.");
                $results[$relView] = ['status' => 'already_compatible', 'message' => 'Visualizador CCD ya presente'];
            } else {
                if ($apply) {
                    if ($backupDir !== null) {
                        $bakFile = $backupDir.'/modified_files/'.$relView;
                        @mkdir(dirname($bakFile), 0775, true);
                        @copy($tgtView, $bakFile);
                    }

                    if (str_contains($content, '<span class="text-slate-400" aria-label="Sin CCD asociado de forma inequívoca">—</span>')) {
                        $viewSnippet = <<<'BLADE'
                                    @php
                                        $ccdConfirmados = collect($c->ccdConfirmados ?? []);
                                    @endphp
                                    @if($ccdConfirmados->isNotEmpty())
                                        <div class="flex flex-wrap justify-center gap-1">
                                            @foreach($ccdConfirmados as $document)
                                                @php
                                                    $name = filled($document->pdf)
                                                        ? pathinfo((string) $document->pdf, PATHINFO_FILENAME)
                                                        : (($document->cosecha ? 'CCD '.$document->cosecha : 'CCD').' #'.$document->id);
                                                @endphp
                                                <a
                                                    href="{{ route('clientes.ccd.show', ['numdoc' => $dni, 'ccdCliente' => $document->id]) }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex size-7 items-center justify-center rounded-md text-kp-secondary transition-colors hover:bg-kp-secondary-soft hover:text-kp-secondary-dark kp-focus"
                                                    title="Abrir {{ $name }}"
                                                    aria-label="Abrir {{ $name }}"
                                                >
                                                    <x-ui.registration-paper-icon class="size-5" />
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-400" aria-label="Sin CCD confirmado para esta operación">—</span>
                                    @endif
BLADE;
                        $modified = str_replace(
                            '<span class="text-slate-400" aria-label="Sin CCD asociado de forma inequívoca">—</span>',
                            $viewSnippet,
                            $content
                        );
                        file_put_contents($tgtView, $modified);
                        $log("Visualizador CCD integrado en {$relView} (arquitectura moderna)");
                        $results[$relView] = ['status' => 'additively_integrated', 'message' => 'Visualizador CCD integrado'];
                    } elseif (str_contains($content, '{{-- === CELDA CCD === --}}') && str_contains($content, '{{-- === /CELDA CCD === --}}')) {
                        $pattern = '/(\{\{--\s*===\s*CELDA CCD\s*===\s*--\}\}\s*<td[^>]*>)(.*?)(\s*<\/td>\s*\{\{--\s*===\s*\/CELDA CCD\s*===\s*--\}\})/s';
                        $legacyReplacement = <<<'BLADE'
$1
                @php
                  $confirmados = collect($cta->ccdConfirmados ?? $c->ccdConfirmados ?? []);
                @endphp
                @if($confirmados->isNotEmpty())
                  @if($confirmados->count() === 1)
                    @php
                      $d = $confirmados->first();
                      $name = filled($d->pdf) ? pathinfo((string)$d->pdf, PATHINFO_FILENAME) : (($d->cosecha ? 'CCD '.$d->cosecha : 'CCD').' #'.$d->id);
                    @endphp
                    <a href="{{ route('clientes.ccd.show', ['numdoc' => $dni, 'ccdCliente' => $d->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Abrir {{ $name }}">
                      <i class="bi bi-filetype-pdf me-1"></i> {{ $name }}
                    </a>
                  @else
                    <div class="btn-group">
                      <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-filetype-pdf me-1"></i> CCD ({{ $confirmados->count() }})
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        @foreach($confirmados as $d)
                          @php
                            $name = filled($d->pdf) ? pathinfo((string)$d->pdf, PATHINFO_FILENAME) : (($d->cosecha ? 'CCD '.$d->cosecha : 'CCD').' #'.$d->id);
                          @endphp
                          <li><a class="dropdown-item" href="{{ route('clientes.ccd.show', ['numdoc' => $dni, 'ccdCliente' => $d->id]) }}" target="_blank">{{ $name }}</a></li>
                        @endforeach
                      </ul>
                    </div>
                  @endif
                @else
$2
                @endif
$3
BLADE;
                        $modified = preg_replace($pattern, $legacyReplacement, $content);
                        if ($modified === null || $modified === $content) {
                            throw new DomainException("Error al inyectar visualizador CCD en celda legacy de {$relView}.");
                        }
                        file_put_contents($tgtView, $modified);
                        $log("Visualizador CCD integrado en {$relView} (arquitectura legacy)");
                        $results[$relView] = ['status' => 'additively_integrated', 'message' => 'Visualizador CCD integrado en celda legacy'];
                    } else {
                        throw new DomainException("Incompatibilidad crítica en {$relView}: no se encontró la celda placeholder para CCD.");
                    }
                } else {
                    $log("[DRY-RUN] Vista {$relView} compatible: requiere integración de visualizador CCD");
                    $results[$relView] = ['status' => 'pending_additive_integration', 'message' => 'Requiere integración de visualizador CCD'];
                }
            }
        }

        // 7. Configuración de filesystems config/filesystems.php
        $relFs = 'config/filesystems.php';
        $tgtFs = $targetDir.'/'.$relFs;

        if (! is_file($tgtFs)) {
            throw new DomainException("El archivo {$relFs} no existe en destino.");
        }

        $fsContent = (string) file_get_contents($tgtFs);
        if (str_contains($fsContent, "'ccd-private'") || str_contains($fsContent, '"ccd-private"')) {
            $log("Disco ccd-private ya configurado en {$relFs}; conservado intacto.");
            $results[$relFs] = ['status' => 'already_configured', 'message' => 'Disco ccd-private ya configurado'];
        } else {
            if (! str_contains($fsContent, "'disks' => [") && ! str_contains($fsContent, '"disks" => [')) {
                throw new DomainException("Incompatibilidad en {$relFs}: sección 'disks' no encontrada.");
            }

            $this->assertPhpSyntax($tgtFs);
            if ($apply) {
                if ($backupDir !== null) {
                    $bakFile = $backupDir.'/modified_files/'.$relFs;
                    @mkdir(dirname($bakFile), 0775, true);
                    @copy($tgtFs, $bakFile);
                }

                $diskConfig = <<<'PHP'
        'ccd-private' => [
            'driver' => 'local',
            'root' => storage_path('app/private/ccd'),
            'visibility' => 'private',
            'directory_visibility' => 'private',
            'permissions' => [
                'file' => [
                    'public' => 0660,
                    'private' => 0660,
                ],
                'dir' => [
                    'public' => 0770,
                    'private' => 0770,
                ],
            ],
            'throw' => true,
        ],

PHP;
                $pattern = "/('disks'\s*=>\s*\[)/";
                if (! preg_match($pattern, $fsContent)) {
                    $pattern = '/("disks"\s*=>\s*\[)/';
                }
                $modified = preg_replace($pattern, "$1\n".$diskConfig, $fsContent, 1) ?? $fsContent;

                file_put_contents($tgtFs, $modified);
                $this->assertPhpSyntax($tgtFs);
                $log("Configurado disco ccd-private en {$relFs}");
                $results[$relFs] = ['status' => 'additively_integrated', 'message' => 'Disco ccd-private configurado'];
            } else {
                $log("[DRY-RUN] Configuración {$relFs} compatible: requiere disco ccd-private");
                $results[$relFs] = ['status' => 'pending_additive_integration', 'message' => 'Requiere configuración de disco ccd-private'];
            }
        }

        return $results;
    }

    /**
     * Valida la seguridad previa de reversión para evitar eliminar migraciones ajenas o datos posteriores.
     *
     * @return array{safe: bool, reasons: list<string>, warnings: list<string>}
     */
    public function validateRollbackSafety(PDO $targetPdo): array
    {
        $reasons = [];
        $warnings = [];

        $hasMigrations = $this->tableExists($targetPdo, 'migrations');
        if (! $hasMigrations) {
            return [
                'safe' => true,
                'reasons' => [],
                'warnings' => ['No existe tabla migrations en destino.'],
            ];
        }

        // 1. Verificar registros de migraciones CCD
        $stmt = $targetPdo->query(
            "SELECT id, migration, batch FROM migrations
             WHERE migration LIKE '%create_ccd_archivos%'
                OR migration LIKE '%create_ccd_cliente_cuenta%'"
        );
        $ccdMigrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($ccdMigrations)) {
            $warnings[] = 'No se encontraron migraciones CCD registradas en la tabla migrations.';
        } else {
            $ccdBatches = array_unique(array_column($ccdMigrations, 'batch'));

            if (! empty($ccdBatches)) {
                $placeholders = implode(',', array_fill(0, count($ccdBatches), '?'));
                $sharedStmt = $targetPdo->prepare(
                    "SELECT migration, batch FROM migrations
                     WHERE batch IN ({$placeholders})
                       AND migration NOT LIKE '%create_ccd_archivos%'
                       AND migration NOT LIKE '%create_ccd_cliente_cuenta%'"
                );
                $sharedStmt->execute(array_values($ccdBatches));
                $sharedMigrations = $sharedStmt->fetchAll(PDO::FETCH_ASSOC);

                if (! empty($sharedMigrations)) {
                    $names = implode(', ', array_column($sharedMigrations, 'migration'));
                    $reasons[] = "Otras migraciones no-CCD comparten el mismo lote (batch) que CCD: {$names}. Debe usarse reversión selectiva por ruta para no afectarlas.";
                }

                $maxBatch = max($ccdBatches);
                $subsequentStmt = $targetPdo->prepare('SELECT migration, batch FROM migrations WHERE batch > ?');
                $subsequentStmt->execute([$maxBatch]);
                $subsequentMigrations = $subsequentStmt->fetchAll(PDO::FETCH_ASSOC);

                if (! empty($subsequentMigrations)) {
                    $names = implode(', ', array_column($subsequentMigrations, 'migration'));
                    $reasons[] = "Existen migraciones posteriores a CCD aplicadas en destino (lote > {$maxBatch}): {$names}. Revertir CCD podría invalidar dependencias de esquema.";
                }
            }
        }

        // 2. Asociaciones de otras cosechas
        if ($this->tableExists($targetPdo, 'ccd_cliente_cuenta')) {
            $otherHarvestAssoc = (int) $this->fetchScalar(
                $targetPdo,
                "SELECT COUNT(*) FROM ccd_cliente_cuenta cca
                 JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
                 WHERE cc.cosecha NOT IN ('AQP4', 'AQP5')"
            );

            if ($otherHarvestAssoc > 0) {
                $reasons[] = "ccd_cliente_cuenta contiene {$otherHarvestAssoc} asociaciones correspondientes a cosechas distintas de AQP4/AQP5. No se puede eliminar la tabla sin perder datos de producción.";
            }
        }

        // 3. Archivos vinculados a otras cosechas
        if ($this->tableExists($targetPdo, 'ccd_archivos') && $this->columnExists($targetPdo, 'ccd_clientes', 'ccd_archivo_id')) {
            $otherHarvestFiles = (int) $this->fetchScalar(
                $targetPdo,
                "SELECT COUNT(*) FROM ccd_clientes cc
                 WHERE cc.cosecha NOT IN ('AQP4', 'AQP5')
                   AND cc.ccd_archivo_id IS NOT NULL"
            );

            if ($otherHarvestFiles > 0) {
                $reasons[] = "ccd_archivos contiene archivos vinculados a clientes de cosechas distintas a AQP4/AQP5 ({$otherHarvestFiles} referencias).";
            }
        }

        // 4. Claves foráneas de terceras tablas
        $dbName = $this->fetchScalar($targetPdo, 'SELECT DATABASE()');
        if (is_string($dbName) && $dbName !== '') {
            $stmt = $targetPdo->prepare(
                "SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ?
                   AND REFERENCED_TABLE_NAME IN ('ccd_archivos', 'ccd_cliente_cuenta')
                   AND TABLE_NAME NOT IN ('ccd_archivos', 'ccd_cliente_cuenta', 'ccd_clientes')"
            );
            $stmt->execute([$dbName]);
            $fkDeps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (! empty($fkDeps)) {
                $depList = [];
                foreach ($fkDeps as $fk) {
                    $depList[] = "{$fk['TABLE_NAME']}.{$fk['CONSTRAINT_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}";
                }
                $reasons[] = 'Existen tablas ajenas con claves foráneas apuntando a tablas CCD: '.implode(', ', $depList);
            }
        }

        return [
            'safe' => empty($reasons),
            'reasons' => $reasons,
            'warnings' => $warnings,
        ];
    }

    public function assertPhpSyntax(string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw new DomainException("El archivo a validar no existe: {$filePath}");
        }

        $phpBinary = PHP_BINARY ?: 'php';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open([$phpBinary, '-l', $filePath], $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $returnCode = proc_close($process);

            if ($returnCode !== 0) {
                $err = trim($stderr !== '' ? $stderr : $stdout);
                throw new DomainException("Error de sintaxis PHP detectado en {$filePath}:\n{$err}");
            }

            return;
        }

        try {
            $code = (string) file_get_contents($filePath);
            token_get_all($code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            throw new DomainException("Error de sintaxis PHP detectado en {$filePath}:\n".$e->getMessage());
        }
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);

        return $stmt->fetch() !== false;
    }

    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if (! $this->tableExists($pdo, $table)) {
            return false;
        }
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);

        return $stmt->fetch() !== false;
    }

    private function assertTableExists(PDO $pdo, string $table, string $env): void
    {
        if (! $this->tableExists($pdo, $table)) {
            throw new DomainException("La tabla requerida `{$table}` no existe en {$env}.");
        }
    }

    private function fetchScalar(PDO $pdo, string $sql): mixed
    {
        $stmt = $pdo->query($sql);

        return $stmt->fetchColumn();
    }
}
