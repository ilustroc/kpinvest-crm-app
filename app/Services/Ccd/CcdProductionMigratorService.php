<?php

namespace App\Services\Ccd;

use DomainException;
use PDO;
use RuntimeException;
use Throwable;

class CcdProductionMigratorService
{
    public const EXPECTED_TOTAL_CCD = 275_987;

    public const EXPECTED_TOTAL_CUENTAS = 282_595;

    public const EXPECTED_AQP_TOTAL = 10_224;

    public const EXPECTED_AQP4_TOTAL = 7_024;

    public const EXPECTED_AQP5_TOTAL = 3_200;

    public const EXPECTED_V3_METHOD_TOTAL = 1;

    public const EXPECTED_V5_METHOD_TOTAL = 10_223;

    public const METHOD_V3 = 'operacion_pdf_aqp_auditada_v3';

    public const METHOD_V5 = 'operacion_pdf_aqp_auditada_v5';

    public const EXPECTED_CCD_AQP_SHA256 = '861b054ff5ca5027f927d95d8c9980381bb6d3cd69f325ec019b3650506b568b';

    public const EXPECTED_CUENTAS_SHA256 = '0a66ff254fd82cfa021cca872404686ee15785d8231e34eae0ae8711076bbd6f';

    public const MIN_FREE_SPACE_BYTES = 5_368_709_120; // 5 GiB

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
     *   logger?: ?callable(string): void
     * }  $config
     * @return array{
     *   status: string,
     *   mode: string,
     *   preflight: array<string, mixed>,
     *   files_transferred: int,
     *   files_verified: int,
     *   archivos_migrated: int,
     *   links_updated: int,
     *   associations_migrated: int,
     *   audit: array<string, mixed>,
     *   manifest_path: ?string,
     *   manifest_sha256: ?string
     * }
     */
    public function migrate(array $config): array
    {
        $sourcePdo = $config['source_pdo'];
        $targetPdo = $config['target_pdo'];
        $sourceStorage = rtrim($config['source_storage'], '/\\');
        $targetStorage = rtrim($config['target_storage'], '/\\');
        $apply = (bool) ($config['apply'] ?? false);
        $batchSize = max(1, (int) ($config['batch_size'] ?? 500));
        $skipFiles = (bool) ($config['skip_files'] ?? false);
        $manifestPath = $config['manifest_path'] ?? null;
        $expectedTotal = isset($config['expected_total']) ? (int) $config['expected_total'] : self::EXPECTED_AQP_TOTAL;
        $logger = $config['logger'] ?? static function (string $msg): void {};

        $logger('Iniciando verificación preflight de bases de datos...');
        $preflight = $this->validatePreflight($sourcePdo, $targetPdo, $logger);

        if (! $skipFiles) {
            $logger('Verificando espacio libre en disco de destino...');
            $this->checkDiskSpace($targetStorage, self::MIN_FREE_SPACE_BYTES);
        }

        $filesTransferred = 0;
        $filesVerified = 0;
        $archivosMigrated = 0;
        $linksUpdated = 0;
        $associationsMigrated = 0;

        if (! $skipFiles) {
            $logger('Iniciando transferencia y verificación de PDFs AQP...');
            $fileResults = $this->transferAndVerifyFiles(
                $sourcePdo,
                $sourceStorage,
                $targetStorage,
                $apply,
                $logger
            );
            $filesTransferred = $fileResults['transferred'];
            $filesVerified = $fileResults['verified'];
        }

        $logger('Procesando migración de registros ccd_archivos...');
        $archivosMigrated = $this->migrateCcdArchivos($sourcePdo, $targetPdo, $apply, $batchSize, $logger, $expectedTotal);

        $logger('Actualizando vinculación ccd_clientes.ccd_archivo_id...');
        $linksUpdated = $this->linkCcdArchivoId($sourcePdo, $targetPdo, $apply, $batchSize, $logger, $expectedTotal);

        $logger('Validando coherencia de asociaciones antes de persistencia...');
        $this->validateAssociationsPreInsert($sourcePdo, $targetPdo, $expectedTotal);

        $logger('Migrando asociaciones confirmadas a ccd_cliente_cuenta...');
        $associationsMigrated = $this->migrateAssociations($sourcePdo, $targetPdo, $apply, $batchSize, $logger, $expectedTotal);

        $logger('Ejecutando auditoría integral post-migración...');
        $audit = $this->auditTarget($targetPdo, $targetStorage, $skipFiles);

        $manifestSha256 = null;
        if ($manifestPath !== null) {
            $logger('Generando manifiesto criptográfico de migración...');
            $manifestSha256 = $this->generateManifest($sourcePdo, $targetPdo, $manifestPath, $expectedTotal);
            $logger("Manifiesto generado con SHA-256: {$manifestSha256}");
        }

        $logger('Proceso completado con éxito.');

        return [
            'status' => 'success',
            'mode' => $apply ? 'apply' : 'dry-run',
            'preflight' => $preflight,
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
    public function validatePreflight(PDO $sourcePdo, PDO $targetPdo, ?callable $logger = null): array
    {
        $log = $logger ?? static function (string $msg): void {};

        // 1. Tablas requeridas
        $this->assertTableExists($sourcePdo, 'ccd_clientes', 'origen');
        $this->assertTableExists($sourcePdo, 'clientes_cuentas', 'origen');
        $this->assertTableExists($sourcePdo, 'ccd_archivos', 'origen');
        $this->assertTableExists($sourcePdo, 'ccd_cliente_cuenta', 'origen');

        $this->assertTableExists($targetPdo, 'ccd_clientes', 'destino');
        $this->assertTableExists($targetPdo, 'clientes_cuentas', 'destino');

        // 2. Conteo de ccd_clientes
        $srcCcdCount = (int) $this->fetchScalar($sourcePdo, 'SELECT COUNT(*) FROM ccd_clientes');
        $tgtCcdCount = (int) $this->fetchScalar($targetPdo, 'SELECT COUNT(*) FROM ccd_clientes');

        if ($srcCcdCount !== self::EXPECTED_TOTAL_CCD || $tgtCcdCount !== self::EXPECTED_TOTAL_CCD) {
            throw new DomainException(
                "Discrepancia en conteo total de ccd_clientes (origen: {$srcCcdCount}, destino: {$tgtCcdCount}, esperado: ".self::EXPECTED_TOTAL_CCD.').'
            );
        }

        // 3. Conteo de clientes_cuentas
        $srcCuentasCount = (int) $this->fetchScalar($sourcePdo, 'SELECT COUNT(*) FROM clientes_cuentas');
        $tgtCuentasCount = (int) $this->fetchScalar($targetPdo, 'SELECT COUNT(*) FROM clientes_cuentas');

        if ($srcCuentasCount !== self::EXPECTED_TOTAL_CUENTAS || $tgtCuentasCount !== self::EXPECTED_TOTAL_CUENTAS) {
            throw new DomainException(
                "Discrepancia en conteo total de clientes_cuentas (origen: {$srcCuentasCount}, destino: {$tgtCuentasCount}, esperado: ".self::EXPECTED_TOTAL_CUENTAS.').'
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
                "Discrepancia en conteo AQP ccd_clientes (origen: AQP4={$srcAqp4}, AQP5={$srcAqp5}; destino: AQP4={$tgtAqp4}, AQP5={$tgtAqp5})."
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
        $log('Calculando firmas SHA-256 de tablas CCD y Cuentas...');
        $srcCcdSha = $this->calculateCcdAqpSha256($sourcePdo);
        $tgtCcdSha = $this->calculateCcdAqpSha256($targetPdo);

        if (! hash_equals($srcCcdSha, $tgtCcdSha)) {
            throw new DomainException(
                "Las firmas SHA-256 de ccd_clientes AQP no coinciden entre origen ({$srcCcdSha}) y destino ({$tgtCcdSha})."
            );
        }

        $srcCuentasSha = $this->calculateCuentasAqpSha256($sourcePdo);
        $tgtCuentasSha = $this->calculateCuentasAqpSha256($targetPdo);

        if (! hash_equals($srcCuentasSha, $tgtCuentasSha)) {
            throw new DomainException(
                "Las firmas SHA-256 de clientes_cuentas AQP no coinciden entre origen ({$srcCuentasSha}) y destino ({$tgtCuentasSha})."
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
            'staging_associations_total' => $srcAssocTotal,
            'staging_v3_method' => $srcV3Count,
            'staging_v5_method' => $srcV5Count,
            'staging_cataloged_files' => $srcCatalogedFiles,
        ];
    }

    public function checkDiskSpace(string $targetStoragePath, int $minBytes): void
    {
        if (! is_dir($targetStoragePath)) {
            @mkdir($targetStoragePath, 0770, true);
        }

        $free = @disk_free_space($targetStoragePath);

        if ($free !== false && $free < $minBytes) {
            $freeGiB = round($free / 1_073_741_824, 2);
            $requiredGiB = round($minBytes / 1_073_741_824, 2);
            throw new DomainException("Espacio insuficiente en disco destino: {$freeGiB} GiB disponibles, {$requiredGiB} GiB requeridos.");
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
        ?callable $logger = null
    ): array {
        $log = $logger ?? static function (string $msg): void {};

        $stmt = $sourcePdo->query(
            "SELECT ca.id, ca.path, ca.sha256, ca.size_bytes
             FROM ccd_archivos ca
             JOIN ccd_clientes cc ON cc.ccd_archivo_id = ca.id
             WHERE cc.cosecha IN ('AQP4', 'AQP5')
             ORDER BY ca.id"
        );

        $transferred = 0;
        $verified = 0;
        $index = 0;

        while ($file = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
                // Modo dry-run: verificar archivo origen
                $srcSha = hash_file('sha256', $srcFile);
                if (! hash_equals($expectedSha, (string) $srcSha)) {
                    throw new DomainException("Huella corrupta en archivo origen {$srcFile} (esperada: {$expectedSha}, calculada: {$srcSha})");
                }
                $verified++;

                continue;
            }

            // Modo apply: copiar y verificar destino
            $tgtDir = dirname($tgtFile);
            if (! is_dir($tgtDir) && ! @mkdir($tgtDir, 0770, true) && ! is_dir($tgtDir)) {
                throw new RuntimeException("No se pudo crear directorio destino: {$tgtDir}");
            }

            if (is_file($tgtFile)) {
                $tgtSha = hash_file('sha256', $tgtFile);
                if (hash_equals($expectedSha, (string) $tgtSha)) {
                    $verified++;

                    continue;
                }
                // Si existe pero la huella no coincide, sobrescribir
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

            if ($index % 1000 === 0) {
                $log("PDFs procesados: {$index}/".self::EXPECTED_AQP_TOTAL);
            }
        }

        return ['transferred' => $transferred, 'verified' => $verified];
    }

    public function migrateCcdArchivos(
        PDO $sourcePdo,
        PDO $targetPdo,
        bool $apply,
        int $batchSize,
        ?callable $logger = null,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL
    ): int {
        $log = $logger ?? static function (string $msg): void {};

        $stmt = $sourcePdo->query(
            "SELECT ca.id, ca.disk, ca.path, ca.sha256, ca.size_bytes, ca.mime_type, ca.nombre_original, ca.verified_at, ca.created_at, ca.updated_at
             FROM ccd_archivos ca
             JOIN ccd_clientes cc ON cc.ccd_archivo_id = ca.id
             WHERE cc.cosecha IN ('AQP4', 'AQP5')
             ORDER BY ca.id"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = count($rows);

        if ($expectedTotal !== null && $total !== $expectedTotal) {
            throw new DomainException("Se esperaban {$expectedTotal} registros en ccd_archivos, encontrados: {$total}.");
        }

        if (! $apply) {
            return $total;
        }

        $migrated = 0;
        $chunks = array_chunk($rows, $batchSize);

        $insertSql = 'INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                        disk = VALUES(disk),
                        path = VALUES(path),
                        sha256 = VALUES(sha256),
                        size_bytes = VALUES(size_bytes),
                        mime_type = VALUES(mime_type),
                        nombre_original = VALUES(nombre_original),
                        verified_at = VALUES(verified_at)';

        foreach ($chunks as $chunk) {
            $targetPdo->beginTransaction();
            try {
                $insertStmt = $targetPdo->prepare($insertSql);
                foreach ($chunk as $row) {
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
                $targetPdo->rollBack();
                throw new RuntimeException("Fallo al insertar lote de ccd_archivos: {$e->getMessage()}", 0, $e);
            }
        }

        $log("Registros ccd_archivos migrados: {$migrated}");

        return $migrated;
    }

    public function linkCcdArchivoId(
        PDO $sourcePdo,
        PDO $targetPdo,
        bool $apply,
        int $batchSize,
        ?callable $logger = null,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL
    ): int {
        $log = $logger ?? static function (string $msg): void {};

        $stmt = $sourcePdo->query(
            "SELECT id, ccd_archivo_id, cosecha
             FROM ccd_clientes
             WHERE cosecha IN ('AQP4', 'AQP5') AND ccd_archivo_id IS NOT NULL
             ORDER BY id"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = count($rows);

        if ($expectedTotal !== null && $total !== $expectedTotal) {
            throw new DomainException("Se esperaban {$expectedTotal} vínculos ccd_archivo_id, encontrados: {$total}.");
        }

        if (! $apply) {
            return $total;
        }

        $updated = 0;
        $chunks = array_chunk($rows, $batchSize);
        $updateSql = 'UPDATE ccd_clientes SET ccd_archivo_id = ? WHERE id = ? AND cosecha = ?';

        foreach ($chunks as $chunk) {
            $targetPdo->beginTransaction();
            try {
                $updateStmt = $targetPdo->prepare($updateSql);
                foreach ($chunk as $row) {
                    $updateStmt->execute([
                        $row['ccd_archivo_id'],
                        $row['id'],
                        $row['cosecha'],
                    ]);
                    $updated++;
                }
                $targetPdo->commit();
            } catch (Throwable $e) {
                $targetPdo->rollBack();
                throw new RuntimeException("Fallo al actualizar lote de ccd_clientes.ccd_archivo_id: {$e->getMessage()}", 0, $e);
            }
        }

        $log("Vínculos ccd_archivo_id actualizados: {$updated}");

        return $updated;
    }

    public function validateAssociationsPreInsert(
        PDO $sourcePdo,
        PDO $targetPdo,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL
    ): void {
        // Validar correspondencia exacta entre asociaciones de staging y registros en destino
        $stmt = $sourcePdo->query(
            "SELECT cca.ccd_cliente_id, cca.cliente_cuenta_id, cc.numdoc AS ccd_numdoc, cc.cosecha AS ccd_cosecha,
                    cu.numdoc AS cuenta_numdoc, cu.cosecha AS cuenta_cosecha
             FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             JOIN clientes_cuentas cu ON cu.id = cca.cliente_cuenta_id
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada'
             ORDER BY cca.id"
        );

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            $ccdId = (int) $row['ccd_cliente_id'];
            $cuentaId = (int) $row['cliente_cuenta_id'];
            $ccdNumdoc = (string) $row['ccd_numdoc'];
            $cuentaNumdoc = (string) $row['cuenta_numdoc'];

            if (! hash_equals($ccdNumdoc, $cuentaNumdoc)) {
                throw new DomainException("Inconsistencia en DNI entre CCD {$ccdId} ({$ccdNumdoc}) y cuenta {$cuentaId} ({$cuentaNumdoc}).");
            }
        }

        if ($expectedTotal !== null && $count !== $expectedTotal) {
            throw new DomainException("La auditoría previa de asociaciones encontró {$count} registros (esperados: {$expectedTotal}).");
        }
    }

    public function migrateAssociations(
        PDO $sourcePdo,
        PDO $targetPdo,
        bool $apply,
        int $batchSize,
        ?callable $logger = null,
        ?int $expectedTotal = self::EXPECTED_AQP_TOTAL
    ): int {
        $log = $logger ?? static function (string $msg): void {};

        $stmt = $sourcePdo->query(
            "SELECT cca.id, cca.ccd_cliente_id, cca.cliente_cuenta_id, cca.estado, cca.metodo_verificacion,
                    cca.evidencia, cca.confirmado_por_user_id, cca.confirmado_at, cca.created_at, cca.updated_at
             FROM ccd_cliente_cuenta cca
             JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id
             WHERE cc.cosecha IN ('AQP4', 'AQP5') AND cca.estado = 'confirmada'
             ORDER BY cca.id"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = count($rows);

        if ($expectedTotal !== null && $total !== $expectedTotal) {
            throw new DomainException("Se esperaban {$expectedTotal} asociaciones confirmadas, encontradas: {$total}.");
        }

        if (! $apply) {
            return $total;
        }

        $migrated = 0;
        $chunks = array_chunk($rows, $batchSize);

        $insertSql = 'INSERT INTO ccd_cliente_cuenta
                      (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_por_user_id, confirmado_at, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                        estado = VALUES(estado),
                        metodo_verificacion = VALUES(metodo_verificacion),
                        evidencia = VALUES(evidencia),
                        confirmado_por_user_id = VALUES(confirmado_por_user_id),
                        confirmado_at = VALUES(confirmado_at)';

        foreach ($chunks as $chunk) {
            $targetPdo->beginTransaction();
            try {
                $insertStmt = $targetPdo->prepare($insertSql);
                foreach ($chunk as $row) {
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
                $targetPdo->rollBack();
                throw new RuntimeException("Fallo al insertar lote de ccd_cliente_cuenta: {$e->getMessage()}", 0, $e);
            }
        }

        $log("Asociaciones migradas a ccd_cliente_cuenta: {$migrated}");

        return $migrated;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditTarget(PDO $targetPdo, string $targetStorage, bool $skipFiles = false): array
    {
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

        $duplicateCcd = (int) $this->fetchScalar(
            $targetPdo,
            "SELECT COUNT(*) FROM (SELECT ccd_cliente_id FROM ccd_cliente_cuenta WHERE estado = 'confirmada' GROUP BY ccd_cliente_id HAVING COUNT(*) > 1) d"
        );

        $duplicateCuenta = (int) $this->fetchScalar(
            $targetPdo,
            "SELECT COUNT(*) FROM (SELECT cliente_cuenta_id FROM ccd_cliente_cuenta WHERE estado = 'confirmada' GROUP BY cliente_cuenta_id HAVING COUNT(*) > 1) d"
        );

        $orphanedCcd = (int) $this->fetchScalar(
            $targetPdo,
            'SELECT COUNT(*) FROM ccd_cliente_cuenta cca LEFT JOIN ccd_clientes cc ON cc.id = cca.ccd_cliente_id WHERE cc.id IS NULL'
        );

        $orphanedCuentas = (int) $this->fetchScalar(
            $targetPdo,
            'SELECT COUNT(*) FROM ccd_cliente_cuenta cca LEFT JOIN clientes_cuentas cu ON cu.id = cca.cliente_cuenta_id WHERE cu.id IS NULL'
        );

        $orphanedArchivos = (int) $this->fetchScalar(
            $targetPdo,
            'SELECT COUNT(*) FROM ccd_clientes cc LEFT JOIN ccd_archivos ca ON ca.id = cc.ccd_archivo_id WHERE cc.ccd_archivo_id IS NOT NULL AND ca.id IS NULL'
        );

        $missingFiles = 0;
        if (! $skipFiles && $archivosCount > 0) {
            $stmt = $targetPdo->query('SELECT path, sha256 FROM ccd_archivos');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fullPath = $targetStorage.'/'.(string) $row['path'];
                if (! is_file($fullPath)) {
                    $missingFiles++;
                }
            }
        }

        return [
            'archivos_count' => $archivosCount,
            'linked_ccd_count' => $linkedCcdCount,
            'unlinked_ccd_count' => $unlinkedCcdCount,
            'assoc_confirmed' => $assocConfirmed,
            'assoc_aqp4' => $assocAqp4,
            'assoc_aqp5' => $assocAqp5,
            'assoc_v3' => $assocV3,
            'assoc_v5' => $assocV5,
            'duplicate_ccd' => $duplicateCcd,
            'duplicate_cuenta' => $duplicateCuenta,
            'orphaned_ccd' => $orphanedCcd,
            'orphaned_cuentas' => $orphanedCuentas,
            'orphaned_archivos' => $orphanedArchivos,
            'missing_files' => $missingFiles,
            'passed' => (
                $duplicateCcd === 0
                && $duplicateCuenta === 0
                && $orphanedCcd === 0
                && $orphanedCuentas === 0
                && $orphanedArchivos === 0
                && $missingFiles === 0
            ),
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
             ORDER BY id"
        );

        $ctx = hash_init('sha256');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            hash_update($ctx, implode("\t", $row)."\n");
        }

        return hash_final($ctx);
    }

    public function calculateCuentasAqpSha256(PDO $pdo): string
    {
        $stmt = $pdo->query(
            "SELECT id, numdoc, cuenta, operacion, entidad, cosecha
             FROM clientes_cuentas
             WHERE cosecha IN ('CAJAAQP4', 'CAJAAQP5')
             ORDER BY id"
        );

        $ctx = hash_init('sha256');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            hash_update($ctx, implode("\t", $row)."\n");
        }

        return hash_final($ctx);
    }

    private function assertTableExists(PDO $pdo, string $table, string $env): void
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        if ($stmt->fetch() === false) {
            throw new DomainException("La tabla requerida `{$table}` no existe en {$env}.");
        }
    }

    private function fetchScalar(PDO $pdo, string $sql): mixed
    {
        $stmt = $pdo->query($sql);

        return $stmt->fetchColumn();
    }
}
