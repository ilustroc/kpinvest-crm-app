<?php

namespace Tests\Feature;

use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use App\Models\User;
use App\Services\Ccd\CcdProductionMigratorService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class CcdAqpProductionMigrationV6Test extends TestCase
{
    use DatabaseTransactions;

    private string $sourceStorage;

    private string $targetStorage;

    private PDO $sourcePdo;

    private PDO $targetPdo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:G2wM+FkO9k5fW8u8j7eR6/fX8B+U2wV3t4r5y6z7A8B=']);

        $this->sourceStorage = storage_path('framework/testing/disks/source_ccd');
        $this->targetStorage = storage_path('framework/testing/disks/target_ccd');

        File::ensureDirectoryExists($this->sourceStorage);

        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port = (int) config('database.connections.mysql.port', 3306);
        $user = (string) config('database.connections.mysql.username', 'forge');
        $pass = (string) config('database.connections.mysql.password', '');

        $this->sourcePdo = new PDO(
            "mysql:host={$host};port={$port};dbname=kp_crm_testing_source;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $this->targetPdo = new PDO(
            "mysql:host={$host};port={$port};dbname=kp_crm_testing_target;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $this->setupSourceSchema($this->sourcePdo);
        $this->setupCleanTargetSchema($this->targetPdo);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceStorage);
        File::deleteDirectory($this->targetStorage);

        parent::tearDown();
    }

    public function test_dry_run_on_clean_target_with_no_ccd_tables_executes_zero_writes_and_reports_pending_migrations(): void
    {
        $service = new CcdProductionMigratorService;

        // Comprobar que destino inicialmente NO tiene tablas CCD nuevas ni columna ccd_archivo_id
        $this->assertFalse($service->tableExists($this->targetPdo, 'ccd_archivos'));
        $this->assertFalse($service->tableExists($this->targetPdo, 'ccd_cliente_cuenta'));
        $this->assertFalse($service->columnExists($this->targetPdo, 'ccd_clientes', 'ccd_archivo_id'));

        // Sembrar 1 registro coherente en origen y destino
        $content = '%PDF-1.4 Simulated Content DryRun';
        $sha256 = hash('sha256', $content);
        $size = strlen($content);
        $relPath = "sha256/11/22/{$sha256}.pdf";
        $srcFile = $this->sourceStorage.'/'.$relPath;
        File::ensureDirectoryExists(dirname($srcFile));
        file_put_contents($srcFile, $content);

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', '{$relPath}', '{$sha256}', {$size}, 'application/pdf', 'doc.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '44556677', 'doc.pdf', 'AQP4', 1)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '44556677', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Verificado en PDF', NOW())");

        // Destino limpio: tiene ccd_clientes y clientes_cuentas pero sin ccd_archivo_id ni ccd_cliente_cuenta
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha)
                                VALUES (1, '44556677', 'doc.pdf', 'AQP4')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '44556677', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4')");

        // Ejecutar dry-run
        $manifestTarget = $this->targetStorage.'/manifest.csv';
        $result = $service->migrate([
            'source_pdo' => $this->sourcePdo,
            'target_pdo' => $this->targetPdo,
            'source_storage' => $this->sourceStorage,
            'target_storage' => $this->targetStorage,
            'apply' => false,
            'manifest_path' => $manifestTarget,
            'expected_total' => 1,
        ]);

        // Verificaciones de simulación
        $this->assertSame('simulated', $result['status']);
        $this->assertSame('dry-run', $result['mode']);
        $this->assertFalse($result['target_schema']['is_ready_for_apply']);
        $this->assertNotEmpty($result['target_schema']['pending_migrations']);
        $this->assertSame(1, $result['files_verified']);
        $this->assertSame(0, $result['files_transferred']);
        $this->assertSame(1, $result['archivos_eligible']);
        $this->assertSame(1, $result['links_eligible']);
        $this->assertSame(1, $result['associations_eligible']);

        // Verificaciones de CERO escrituras en destino
        $this->assertFalse($service->tableExists($this->targetPdo, 'ccd_archivos'));
        $this->assertFalse($service->tableExists($this->targetPdo, 'ccd_cliente_cuenta'));
        $this->assertFalse($service->columnExists($this->targetPdo, 'ccd_clientes', 'ccd_archivo_id'));
        $this->assertFalse(file_exists($manifestTarget));
        $this->assertFalse(file_exists($this->targetStorage.'/'.$relPath));
    }

    public function test_apply_fails_if_target_schema_lacks_ccd_tables(): void
    {
        $service = new CcdProductionMigratorService;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No se puede ejecutar --apply: el esquema de destino no tiene las tablas o columnas CCD');

        $service->migrate([
            'source_pdo' => $this->sourcePdo,
            'target_pdo' => $this->targetPdo,
            'source_storage' => $this->sourceStorage,
            'target_storage' => $this->targetStorage,
            'apply' => true,
            'expected_total' => 1,
        ]);
    }

    public function test_selective_migrations_create_only_ccd_tables_and_columns(): void
    {
        $service = new CcdProductionMigratorService;

        $this->assertFalse($service->inspectTargetSchema($this->targetPdo)['is_ready_for_apply']);

        // Aplicar migraciones selectivas
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        $inspect = $service->inspectTargetSchema($this->targetPdo);
        $this->assertTrue($inspect['is_ready_for_apply']);
        $this->assertTrue($inspect['has_ccd_archivos']);
        $this->assertTrue($inspect['has_ccd_cliente_cuenta']);
        $this->assertTrue($inspect['has_ccd_archivo_id_col']);
    }

    public function test_apply_rejects_incompatible_association_collision_without_modifying_target(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        // Origen quiere asociar CCD 1 con cuenta 1
        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', 'sha256/xx/yy/test.pdf', 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890', 100, 'application/pdf', 't.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '10203040', 't.pdf', 'AQP4', 1)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '10203040', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4'),
                                       (2, '10203040', 'CTA-2', 'OP-2', 'Caja AQP', 'CAJAAQP4')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Evidencia A', NOW())");

        // Destino ya tiene CCD 1 y cuentas 1 y 2
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha) VALUES (1, '10203040', 't.pdf', 'AQP4')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '10203040', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4'),
                                       (2, '10203040', 'CTA-2', 'OP-2', 'Caja AQP', 'CAJAAQP4')");

        // Destino ya tiene una asociación PREVIA INCOMPATIBLE vinculando CCD 1 a cuenta 2
        $this->targetPdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 2, 'confirmada', 'otra_auditoria', 'Evidencia Previa', NOW())");

        // Debe lanzar DomainException y NO modificar la asociación existente
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Conflicto de integridad en ccd_cliente_cuenta');

        $service->migrateAssociations($this->sourcePdo, $this->targetPdo, true, 500, null, 1);

        // Asegurar que la asociación existente en destino conservó sus valores intactos
        $stmt = $this->targetPdo->query('SELECT cliente_cuenta_id, metodo_verificacion FROM ccd_cliente_cuenta WHERE ccd_cliente_id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(2, (int) $row['cliente_cuenta_id']);
        $this->assertSame('otra_auditoria', $row['metodo_verificacion']);
    }

    public function test_apply_is_idempotent_when_association_is_identical(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', 'sha256/xx/yy/test.pdf', 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890', 100, 'application/pdf', 't.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '10203040', 't.pdf', 'AQP4', 1)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '10203040', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Evidencia A', NOW())");

        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha) VALUES (1, '10203040', 't.pdf', 'AQP4')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '10203040', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4')");

        // Destino ya tiene exactamente la misma asociación
        $this->targetPdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Evidencia A', NOW())");

        // Primera llamada: debe considerarla idempotente sin fallar
        $migrated = $service->migrateAssociations($this->sourcePdo, $this->targetPdo, true, 500, null, 1);
        $this->assertSame(1, $migrated);

        $count = (int) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_cliente_cuenta')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_apply_rejects_incompatible_ccd_archivos_and_link_collision(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        // Origen
        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', 'sha256/11/test.pdf', '1111111111111111111111111111111111111111111111111111111111111111', 100, 'application/pdf', 't.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '10203040', 't.pdf', 'AQP4', 1)");

        // Destino con archivo 1 pero con hash diferente, y archivo 2
        $this->targetPdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', 'sha256/11/test.pdf', '9999999999999999999999999999999999999999999999999999999999999999', 100, 'application/pdf', 't.pdf', NOW(), NOW(), NOW()),
                                       (2, 'ccd-private', 'sha256/22/other.pdf', '2222222222222222222222222222222222222222222222222222222222222222', 100, 'application/pdf', 'o.pdf', NOW(), NOW(), NOW())");
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '10203040', 't.pdf', 'AQP4', 2)"); // Apunta a archivo 2 en destino

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Conflicto de integridad en ccd_archivos');

        $service->migrateCcdArchivos($this->sourcePdo, $this->targetPdo, true, 500, null, 1);
    }

    public function test_interrupted_migration_resumes_safely(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        // Origen tiene 2 registros
        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', 'p1.pdf', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 10, 'application/pdf', 'p1.pdf', NOW(), NOW(), NOW()),
                                       (2, 'ccd-private', 'p2.pdf', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 20, 'application/pdf', 'p2.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '11111111', 'p1.pdf', 'AQP4', 1),
                                       (2, '22222222', 'p2.pdf', 'AQP5', 2)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '11111111', 'C1', 'O1', 'Caja AQP', 'CAJAAQP4'),
                                       (2, '22222222', 'C2', 'O2', 'Caja AQP', 'CAJAAQP5')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Ev1', NOW()),
                                       (2, 2, 2, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Ev2', NOW())");

        // Destino tiene los clientes y cuentas base
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha)
                                VALUES (1, '11111111', 'p1.pdf', 'AQP4'),
                                       (2, '22222222', 'p2.pdf', 'AQP5')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '11111111', 'C1', 'O1', 'Caja AQP', 'CAJAAQP4'),
                                       (2, '22222222', 'C2', 'O2', 'Caja AQP', 'CAJAAQP5')");

        // Simular que una corrida previa se interrumpió tras insertar solo el primer registro
        $service->migrateCcdArchivos($this->sourcePdo, $this->targetPdo, true, 1, null, 2);
        $service->linkCcdArchivoId($this->sourcePdo, $this->targetPdo, true, 1, null, 2);
        $this->targetPdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Ev1', NOW())");

        $this->assertSame(1, (int) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_cliente_cuenta')->fetchColumn());

        // Reanudar la migración: debe procesar el faltante y mantener el existente sin duplicar
        $resumedAssoc = $service->migrateAssociations($this->sourcePdo, $this->targetPdo, true, 500, null, 2);
        $this->assertSame(2, $resumedAssoc);

        $this->assertSame(2, (int) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_cliente_cuenta')->fetchColumn());
        $this->assertSame(2, (int) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_archivos')->fetchColumn());
    }

    public function test_credentials_file_reading_and_protection(): void
    {
        $credFile = storage_path('framework/testing/test_creds_'.uniqid().'.json');
        file_put_contents($credFile, json_encode([
            'source' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'kp_crm_testing_source',
                'username' => 'forge',
                'password' => '',
            ],
            'target' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'kp_crm_testing_target',
                'username' => 'forge',
                'password' => '',
            ],
        ]));

        try {
            // El comando debe ejecutarse en dry-run leyendo las credenciales del archivo JSON privado
            $exitCode = $this->artisan('ccd:migrate-aqp-production-v6', [
                '--dry-run' => true,
                '--credentials-file' => $credFile,
                '--source-storage' => $this->sourceStorage,
                '--target-storage' => $this->targetStorage,
                '--skip-files' => true,
            ])->assertExitCode(1); // Fallará en preflight de conteos pero la conexión se establece exitosamente sin contraseñas en CLI
        } finally {
            @unlink($credFile);
        }
    }

    public function test_file_transfer_copies_and_verifies_sha256(): void
    {
        $service = new CcdProductionMigratorService;

        $content = '%PDF-1.4 Simulated PDF Content for AQP';
        $sha256 = hash('sha256', $content);
        $size = strlen($content);
        $relPath = 'sha256/'.substr($sha256, 0, 2).'/'.substr($sha256, 2, 2)."/{$sha256}.pdf";

        $fullSourceFile = $this->sourceStorage.'/'.$relPath;
        File::ensureDirectoryExists(dirname($fullSourceFile));
        file_put_contents($fullSourceFile, $content);

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', '{$relPath}', '{$sha256}', {$size}, 'application/pdf', 'test.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '12345678', 'test.pdf', 'AQP4', 1)");

        // Dry-run: solo verifica en origen, NO crea directorios ni copia a destino
        $dryResult = $service->transferAndVerifyFiles($this->sourcePdo, $this->sourceStorage, $this->targetStorage, false, null, 1);
        $this->assertSame(0, $dryResult['transferred']);
        $this->assertSame(1, $dryResult['verified']);
        $this->assertFalse(file_exists($this->targetStorage.'/'.$relPath));

        // Apply: copia y verifica en destino
        $applyResult = $service->transferAndVerifyFiles($this->sourcePdo, $this->sourceStorage, $this->targetStorage, true, null, 1);
        $this->assertSame(1, $applyResult['transferred']);
        $this->assertSame(1, $applyResult['verified']);
        $this->assertTrue(file_exists($this->targetStorage.'/'.$relPath));
        $this->assertSame($sha256, hash_file('sha256', $this->targetStorage.'/'.$relPath));

        // Idempotencia: segunda ejecución apply sobre archivo existente idéntico
        $idempotentResult = $service->transferAndVerifyFiles($this->sourcePdo, $this->sourceStorage, $this->targetStorage, true, null, 1);
        $this->assertSame(0, $idempotentResult['transferred']);
        $this->assertSame(1, $idempotentResult['verified']);
    }

    public function test_ccd_document_controller_serves_migrated_private_pdf_inline(): void
    {
        Storage::fake('ccd-private');
        config(['ccd.private_disk' => 'ccd-private']);

        $content = '%PDF-1.4 Production Streaming Test';
        $sha256 = hash('sha256', $content);
        $relPath = CcdArchivo::pathForHash($sha256);

        Storage::disk('ccd-private')->put($relPath, $content);

        $archivo = CcdArchivo::create([
            'disk' => 'ccd-private',
            'path' => $relPath,
            'sha256' => $sha256,
            'size_bytes' => strlen($content),
            'mime_type' => 'application/pdf',
            'nombre_original' => 'test_ccd.pdf',
            'verified_at' => now(),
        ]);

        $dni = '77889900';
        $ccd = CcdCliente::create([
            'numdoc' => $dni,
            'pdf' => 'test_ccd.pdf',
            'cosecha' => 'AQP4',
            'ccd_archivo_id' => $archivo->id,
        ]);

        $user = User::factory()->create([
            'active' => true,
        ]);

        Gate::define('view-cliente', fn () => true);

        // Sin autenticación: redirige a login
        $this->get(route('clientes.ccd.show', ['numdoc' => $dni, 'ccdCliente' => $ccd->id]))
            ->assertRedirect(route('login'));

        // Con usuario autenticado: sirve PDF inline con cabeceras de seguridad
        $response = $this->actingAs($user)
            ->get(route('clientes.ccd.show', ['numdoc' => $dni, 'ccdCliente' => $ccd->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename=test_ccd.pdf');
        $this->assertSame($content, $response->streamedContent());

        // IDOR: numdoc no coincide
        $this->actingAs($user)
            ->get(route('clientes.ccd.show', ['numdoc' => '99999999', 'ccdCliente' => $ccd->id]))
            ->assertNotFound();
    }

    private function setupSourceSchema(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('DROP TABLE IF EXISTS ccd_cliente_cuenta');
        $pdo->exec('DROP TABLE IF EXISTS ccd_clientes');
        $pdo->exec('DROP TABLE IF EXISTS clientes_cuentas');
        $pdo->exec('DROP TABLE IF EXISTS ccd_archivos');
        $pdo->exec('DROP TABLE IF EXISTS migrations');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $pdo->exec('CREATE TABLE ccd_archivos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            disk VARCHAR(50) NOT NULL,
            path VARCHAR(500) NOT NULL UNIQUE,
            sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
            size_bytes BIGINT UNSIGNED NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            nombre_original VARCHAR(500) NOT NULL,
            verified_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB');

        $pdo->exec('CREATE TABLE ccd_clientes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            numdoc VARCHAR(30) NOT NULL,
            pdf VARCHAR(500) NULL,
            cosecha VARCHAR(100) NULL,
            link VARCHAR(500) NULL,
            codigo VARCHAR(100) NULL,
            ccd_archivo_id BIGINT UNSIGNED NULL,
            INDEX (numdoc, cosecha),
            INDEX (cosecha, pdf),
            FOREIGN KEY (ccd_archivo_id) REFERENCES ccd_archivos(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB');

        $pdo->exec('CREATE TABLE clientes_cuentas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            numdoc VARCHAR(30) NOT NULL,
            cuenta VARCHAR(100) NULL,
            operacion VARCHAR(100) NULL,
            entidad VARCHAR(100) NULL,
            cosecha VARCHAR(100) NULL,
            INDEX (numdoc),
            INDEX (cosecha)
        ) ENGINE=InnoDB');

        $pdo->exec("CREATE TABLE ccd_cliente_cuenta (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ccd_cliente_id BIGINT UNSIGNED NOT NULL,
            cliente_cuenta_id BIGINT UNSIGNED NOT NULL,
            estado ENUM('candidata', 'confirmada', 'en_revision') DEFAULT 'candidata',
            metodo_verificacion VARCHAR(100) NULL,
            evidencia TEXT NULL,
            confirmado_por_user_id BIGINT UNSIGNED NULL,
            confirmado_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            UNIQUE KEY ccd_cliente_cuenta_unique (ccd_cliente_id, cliente_cuenta_id),
            INDEX (ccd_cliente_id, estado),
            INDEX (cliente_cuenta_id, estado),
            FOREIGN KEY (ccd_cliente_id) REFERENCES ccd_clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (cliente_cuenta_id) REFERENCES clientes_cuentas(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB");
    }

    private function setupCleanTargetSchema(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('DROP TABLE IF EXISTS ccd_cliente_cuenta');
        $pdo->exec('DROP TABLE IF EXISTS ccd_clientes');
        $pdo->exec('DROP TABLE IF EXISTS clientes_cuentas');
        $pdo->exec('DROP TABLE IF EXISTS ccd_archivos');
        $pdo->exec('DROP TABLE IF EXISTS migrations');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Producción actual: tiene ccd_clientes sin ccd_archivo_id y clientes_cuentas
        $pdo->exec('CREATE TABLE ccd_clientes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            numdoc VARCHAR(30) NOT NULL,
            pdf VARCHAR(500) NULL,
            cosecha VARCHAR(100) NULL,
            link VARCHAR(500) NULL,
            codigo VARCHAR(100) NULL,
            INDEX (numdoc, cosecha),
            INDEX (cosecha, pdf)
        ) ENGINE=InnoDB');

        $pdo->exec('CREATE TABLE clientes_cuentas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            numdoc VARCHAR(30) NOT NULL,
            cuenta VARCHAR(100) NULL,
            operacion VARCHAR(100) NULL,
            entidad VARCHAR(100) NULL,
            cosecha VARCHAR(100) NULL,
            INDEX (numdoc),
            INDEX (cosecha)
        ) ENGINE=InnoDB');

        $pdo->exec('CREATE TABLE migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL
        ) ENGINE=InnoDB');
    }

    private function applySelectiveMigrationsToTarget(PDO $pdo): void
    {
        // 1. Migración 2026_10_30_120000: ccd_archivos y ccd_clientes.ccd_archivo_id
        $pdo->exec('CREATE TABLE IF NOT EXISTS ccd_archivos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            disk VARCHAR(50) NOT NULL,
            path VARCHAR(500) NOT NULL UNIQUE,
            sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
            size_bytes BIGINT UNSIGNED NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            nombre_original VARCHAR(500) NOT NULL,
            verified_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB');

        $pdo->exec('ALTER TABLE ccd_clientes ADD COLUMN ccd_archivo_id BIGINT UNSIGNED NULL AFTER codigo');
        $pdo->exec('ALTER TABLE ccd_clientes ADD CONSTRAINT fk_ccd_clientes_archivo FOREIGN KEY (ccd_archivo_id) REFERENCES ccd_archivos(id) ON DELETE RESTRICT');
        $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes', 1)");

        // 2. Migración 2026_11_13_000001: ccd_cliente_cuenta
        $pdo->exec("CREATE TABLE IF NOT EXISTS ccd_cliente_cuenta (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ccd_cliente_id BIGINT UNSIGNED NOT NULL,
            cliente_cuenta_id BIGINT UNSIGNED NOT NULL,
            estado ENUM('candidata', 'confirmada', 'en_revision') DEFAULT 'candidata',
            metodo_verificacion VARCHAR(100) NULL,
            evidencia TEXT NULL,
            confirmado_por_user_id BIGINT UNSIGNED NULL,
            confirmado_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            UNIQUE KEY ccd_cliente_cuenta_unique (ccd_cliente_id, cliente_cuenta_id),
            INDEX (ccd_cliente_id, estado),
            INDEX (cliente_cuenta_id, estado),
            FOREIGN KEY (ccd_cliente_id) REFERENCES ccd_clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (cliente_cuenta_id) REFERENCES clientes_cuentas(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB");
        $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2026_11_13_000001_create_ccd_cliente_cuenta_table', 1)");
    }
}
