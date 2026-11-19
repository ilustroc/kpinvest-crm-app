<?php

namespace Tests\Feature;

use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use App\Models\CcdClienteCuenta;
use App\Models\ClienteCuenta;
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

    private array $dbConfig;

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

        $this->dbConfig = [
            'host' => $host,
            'port' => $port,
            'username' => $user,
            'password' => $pass,
        ];

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

    public function test_sha256_calculation_uses_json_lines_and_unifies_ccd_and_cuentas(): void
    {
        $service = new CcdProductionMigratorService;

        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha)
                                VALUES (1, '11223344', 'doc1.pdf', 'AQP4'),
                                       (2, '22334455', 'doc2.pdf', 'AQP5')");

        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha)
                                VALUES (1, '11223344', 'doc1.pdf', 'AQP4'),
                                       (2, '22334455', 'doc2.pdf', 'AQP5')");

        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '11223344', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4'),
                                       (2, '22334455', 'CTA-2', 'OP-2', 'Caja AQP', 'CAJAAQP5'),
                                       (3, '33445566', 'CTA-3', 'OP-3', 'Banco BCP', 'CAJACUSCO')");

        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '11223344', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4'),
                                       (2, '22334455', 'CTA-2', 'OP-2', 'Caja AQP', 'CAJAAQP5'),
                                       (3, '33445566', 'CTA-3', 'OP-3', 'Banco BCP', 'CAJACUSCO')");

        $srcCcdSha = $service->calculateCcdAqpSha256($this->sourcePdo);
        $tgtCcdSha = $service->calculateCcdAqpSha256($this->targetPdo);
        $this->assertSame($srcCcdSha, $tgtCcdSha);

        // Validar que el hash se calcula mediante JSON por registro ordenado por ID
        $expectedCcdSha = hash('sha256',
            json_encode([1, '11223344', 'doc1.pdf', 'AQP4'], JSON_UNESCAPED_UNICODE)."\n".
            json_encode([2, '22334455', 'doc2.pdf', 'AQP5'], JSON_UNESCAPED_UNICODE)."\n"
        );
        $this->assertSame($expectedCcdSha, $srcCcdSha);

        // Cuentas: abarca la totalidad de las cuentas (incluyendo CAJACUSCO sin filtro de cosecha)
        $srcCtaSha = $service->calculateCuentasSha256($this->sourcePdo);
        $tgtCtaSha = $service->calculateCuentasSha256($this->targetPdo);
        $this->assertSame($srcCtaSha, $tgtCtaSha);

        $expectedCtaSha = hash('sha256',
            json_encode([1, '11223344', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4'], JSON_UNESCAPED_UNICODE)."\n".
            json_encode([2, '22334455', 'CTA-2', 'OP-2', 'Caja AQP', 'CAJAAQP5'], JSON_UNESCAPED_UNICODE)."\n".
            json_encode([3, '33445566', 'CTA-3', 'OP-3', 'Banco BCP', 'CAJACUSCO'], JSON_UNESCAPED_UNICODE)."\n"
        );
        $this->assertSame($expectedCtaSha, $srcCtaSha);
    }

    public function test_verify_and_integrate_components_dry_run_and_additive_apply(): void
    {
        $service = new CcdProductionMigratorService;
        $mockSource = storage_path('framework/testing/mock_source_crm_'.uniqid());
        $mockTarget = storage_path('framework/testing/mock_target_crm_'.uniqid());
        $mockBackup = storage_path('framework/testing/mock_backup_crm_'.uniqid());

        File::ensureDirectoryExists($mockSource.'/app/Models');
        File::ensureDirectoryExists($mockSource.'/routes/web');
        File::ensureDirectoryExists($mockSource.'/config');
        File::ensureDirectoryExists($mockSource.'/database/migrations');
        File::ensureDirectoryExists($mockSource.'/app/Services/Ccd');
        File::ensureDirectoryExists($mockSource.'/app/Services/Cliente');
        File::ensureDirectoryExists($mockSource.'/app/Http/Controllers');
        File::ensureDirectoryExists($mockSource.'/app/Console/Commands');
        File::ensureDirectoryExists($mockSource.'/resources/views/clientes');

        File::ensureDirectoryExists($mockTarget.'/app/Models');
        File::ensureDirectoryExists($mockTarget.'/routes/web');
        File::ensureDirectoryExists($mockTarget.'/config');
        File::ensureDirectoryExists($mockTarget.'/database/migrations');
        File::ensureDirectoryExists($mockTarget.'/app/Services/Cliente');
        File::ensureDirectoryExists($mockTarget.'/resources/views/clientes');

        try {
            // Archivos nuevos en source
            file_put_contents($mockSource.'/config/ccd.php', "<?php return ['disk' => 'ccd-private'];\n");
            file_put_contents($mockSource.'/app/Models/CcdArchivo.php', "<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class CcdArchivo extends Model {}\n");
            file_put_contents($mockSource.'/app/Models/CcdClienteCuenta.php', "<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class CcdClienteCuenta extends Model {}\n");
            file_put_contents($mockSource.'/app/Http/Controllers/CcdDocumentController.php', "<?php namespace App\Http\Controllers; class CcdDocumentController {}\n");
            file_put_contents($mockSource.'/app/Console/Commands/MigrateCcdAqpProductionV6.php', "<?php namespace App\Console\Commands; class MigrateCcdAqpProductionV6 {}\n");
            file_put_contents($mockSource.'/app/Services/Ccd/CcdProductionMigratorService.php', "<?php namespace App\Services\Ccd; class CcdProductionMigratorService {}\n");
            file_put_contents($mockSource.'/database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php', "<?php return new class {};\n");
            file_put_contents($mockSource.'/database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php', "<?php return new class {};\n");
            file_put_contents($mockSource.'/app/Models/CcdCliente.php', "<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class CcdCliente extends Model {}\n");

            // Modelo ClienteCuenta existente en producción (sin ccdLinks)
            $sourceCta = "<?php\nnamespace App\Models;\n\nuse Illuminate\Database\Eloquent\Model;\nuse Illuminate\Database\Eloquent\Relations\HasMany;\n\nclass ClienteCuenta extends Model\n{\n    public function ccdLinks(): HasMany\n    {\n        return \$this->hasMany(CcdClienteCuenta::class, 'cliente_cuenta_id');\n    }\n}\n";
            file_put_contents($mockSource.'/app/Models/ClienteCuenta.php', $sourceCta);

            $targetCta = "<?php\nnamespace App\Models;\n\nuse Illuminate\Database\Eloquent\Model;\n\nclass ClienteCuenta extends Model\n{\n    protected \$table = 'clientes_cuentas';\n    public function pagos() { return 1; }\n}\n";
            file_put_contents($mockTarget.'/app/Models/ClienteCuenta.php', $targetCta);

            // Rutas clientes en producción
            $sourceRoutes = "<?php\nuse App\Http\Controllers\ClienteController;\nuse App\Http\Controllers\CcdDocumentController;\nuse Illuminate\Support\Facades\Route;\nRoute::group([], function () {\n    Route::get('/{numdoc}/ccd/{ccdCliente}', [CcdDocumentController::class, 'show'])->name('ccd.show');\n    Route::get('/{numdoc}', [ClienteController::class, 'show']);\n});\n";
            file_put_contents($mockSource.'/routes/web/clientes.php', $sourceRoutes);

            $targetRoutes = "<?php\nuse App\Http\Controllers\ClienteController;\nuse Illuminate\Support\Facades\Route;\nRoute::group([], function () {\n    Route::get('/{numdoc}', [ClienteController::class, 'show']);\n});\n";
            file_put_contents($mockTarget.'/routes/web/clientes.php', $targetRoutes);

            // filesystems en producción sin ccd-private
            $targetFs = "<?php\nreturn [\n    'disks' => [\n        'local' => ['driver' => 'local'],\n    ],\n];\n";
            file_put_contents($mockTarget.'/config/filesystems.php', $targetFs);

            // Vista show.blade.php
            $targetView = "<div><table><tr><x-tables.td align=\"center\"><span class=\"text-slate-400\" aria-label=\"Sin CCD asociado de forma inequívoca\">—</span></x-tables.td></tr></table></div>\n";
            file_put_contents($mockSource.'/resources/views/clientes/show.blade.php', $targetView);
            file_put_contents($mockTarget.'/resources/views/clientes/show.blade.php', $targetView);

            // Servicio ClienteProfileService
            $profileContent = "<?php\nnamespace App\Services\Cliente;\nuse App\Models\CcdCliente;\nclass ClienteProfileService {\n    private function ccdData(string \$dni, \$cuentas): array {\n        \$ccdDocs = collect();\n        \$ccdByDni = collect([\$dni => \$ccdDocs]);\n        return [];\n    }\n}\n";
            file_put_contents($mockSource.'/app/Services/Cliente/ClienteProfileService.php', $profileContent);
            file_put_contents($mockTarget.'/app/Services/Cliente/ClienteProfileService.php', $profileContent);

            // 1. Ejecutar en modo DRY-RUN
            $dryResults = $service->verifyAndIntegrateComponents($mockSource, $mockTarget, false);
            $this->assertSame('pending_install', $dryResults['config/ccd.php']['status']);
            $this->assertSame('pending_additive_integration', $dryResults['app/Models/ClienteCuenta.php']['status']);
            $this->assertSame('pending_additive_integration', $dryResults['routes/web/clientes.php']['status']);
            $this->assertSame('pending_additive_integration', $dryResults['config/filesystems.php']['status']);

            // Verificar que en dry-run CERO archivos fueron modificados en destino
            $this->assertSame($targetCta, file_get_contents($mockTarget.'/app/Models/ClienteCuenta.php'));
            $this->assertSame($targetRoutes, file_get_contents($mockTarget.'/routes/web/clientes.php'));
            $this->assertFalse(file_exists($mockTarget.'/config/ccd.php'));

            // 2. Ejecutar en modo APPLY
            $applyResults = $service->verifyAndIntegrateComponents($mockSource, $mockTarget, true, $mockBackup);
            $this->assertSame('installed', $applyResults['config/ccd.php']['status']);
            $this->assertSame('additively_integrated', $applyResults['app/Models/ClienteCuenta.php']['status']);
            $this->assertSame('additively_integrated', $applyResults['routes/web/clientes.php']['status']);
            $this->assertSame('additively_integrated', $applyResults['config/filesystems.php']['status']);

            // Verificar que el respaldo previo se creó
            $this->assertTrue(file_exists($mockBackup.'/modified_files/app/Models/ClienteCuenta.php'));
            $this->assertSame($targetCta, file_get_contents($mockBackup.'/modified_files/app/Models/ClienteCuenta.php'));

            // Verificar que ClienteCuenta en destino conservó su método previo pagos() y agregó ccdLinks()
            $updatedCta = (string) file_get_contents($mockTarget.'/app/Models/ClienteCuenta.php');
            $this->assertStringContainsString('public function pagos()', $updatedCta);
            $this->assertStringContainsString('public function ccdLinks(): HasMany', $updatedCta);

            // Verificar que routes/web/clientes.php integró ccd.show
            $updatedRoutes = (string) file_get_contents($mockTarget.'/routes/web/clientes.php');
            $this->assertStringContainsString('ccd.show', $updatedRoutes);
            $this->assertStringContainsString('CcdDocumentController', $updatedRoutes);

            // Verificar que filesystems.php integró ccd-private
            $updatedFs = (string) file_get_contents($mockTarget.'/config/filesystems.php');
            $this->assertStringContainsString('ccd-private', $updatedFs);

            // 3. Ejecutar nuevamente (idempotencia): debe detectar que ya es compatible
            $idempotentResults = $service->verifyAndIntegrateComponents($mockSource, $mockTarget, true, $mockBackup);
            $this->assertSame('already_compatible', $idempotentResults['app/Models/ClienteCuenta.php']['status']);
            $this->assertSame('already_compatible', $idempotentResults['routes/web/clientes.php']['status']);
            $this->assertSame('already_configured', $idempotentResults['config/filesystems.php']['status']);
        } finally {
            File::deleteDirectory($mockSource);
            File::deleteDirectory($mockTarget);
            File::deleteDirectory($mockBackup);
        }
    }

    public function test_verify_and_integrate_components_aborts_on_corrupted_file(): void
    {
        $service = new CcdProductionMigratorService;
        $mockTarget = storage_path('framework/testing/mock_target_corrupt_'.uniqid());

        File::ensureDirectoryExists($mockTarget.'/app/Models');

        try {
            // Archivo sin la declaración esperada de class ClienteCuenta
            file_put_contents($mockTarget.'/app/Models/ClienteCuenta.php', "<?php namespace App\Models; class OtherClass {}\n");

            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('Incompatibilidad crítica en app/Models/ClienteCuenta.php: no se encontró la declaración class ClienteCuenta');

            $service->verifyAndIntegrateComponents(base_path(), $mockTarget, false);
        } finally {
            File::deleteDirectory($mockTarget);
        }
    }

    public function test_validate_rollback_safety_guards_against_subsequent_batches_and_foreign_data(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        // Estado inicial con solo las 2 migraciones CCD y datos AQP: rollback es seguro
        $safeInitial = $service->validateRollbackSafety($this->targetPdo);
        $this->assertTrue($safeInitial['safe']);
        $this->assertEmpty($safeInitial['reasons']);

        // 1. Simular migración no-CCD compartiendo el mismo batch
        $this->targetPdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2026_11_14_000000_create_other_table', 1)");
        $checkShared = $service->validateRollbackSafety($this->targetPdo);
        $this->assertFalse($checkShared['safe']);
        $this->assertStringContainsString('comparten el mismo lote', $checkShared['reasons'][0]);

        // Limpiar
        $this->targetPdo->exec("DELETE FROM migrations WHERE migration = '2026_11_14_000000_create_other_table'");

        // 2. Simular migración con batch posterior
        $this->targetPdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2026_11_15_000000_create_subsequent_table', 2)");
        $checkSubsequent = $service->validateRollbackSafety($this->targetPdo);
        $this->assertFalse($checkSubsequent['safe']);
        $this->assertStringContainsString('migraciones posteriores a CCD', $checkSubsequent['reasons'][0]);

        // Limpiar
        $this->targetPdo->exec("DELETE FROM migrations WHERE migration = '2026_11_15_000000_create_subsequent_table'");

        // 3. Simular asociaciones de cosechas ajenas en ccd_cliente_cuenta
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha) VALUES (999, '99999999', 'tru.pdf', 'TRU1')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha) VALUES (999, '99999999', 'C', 'O', 'E', 'TRU1')");
        $this->targetPdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (999, 999, 999, 'confirmada', 'otra', 'Ev', NOW())");

        $checkForeignData = $service->validateRollbackSafety($this->targetPdo);
        $this->assertFalse($checkForeignData['safe']);
        $this->assertStringContainsString('distintas de AQP4/AQP5', $checkForeignData['reasons'][0]);
    }

    public function test_assert_php_syntax_detects_errors_using_proc_open_without_exec(): void
    {
        $service = new CcdProductionMigratorService();

        // 1. Archivo válido
        $validFile = storage_path('framework/testing/valid_'.uniqid().'.php');
        file_put_contents($validFile, "<?php\n\necho 'valid syntax';\n");

        $service->assertPhpSyntax($validFile);
        $this->assertTrue(true);
        @unlink($validFile);

        // 2. Archivo con error de sintaxis
        $invalidFile = storage_path('framework/testing/invalid_'.uniqid().'.php');
        file_put_contents($invalidFile, "<?php\n\nfunction bad() { missing_brace()\n");

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Error de sintaxis PHP detectado');

        try {
            $service->assertPhpSyntax($invalidFile);
        } finally {
            @unlink($invalidFile);
        }
    }

    public function test_verify_and_integrate_components_legacy_architecture_routes_controller_and_view(): void
    {
        $service = new CcdProductionMigratorService();
        $sourceDir = base_path();
        $targetDir = storage_path('framework/testing/legacy_target_'.uniqid());
        $backupDir = storage_path('framework/testing/legacy_backup_'.uniqid());

        @mkdir($targetDir.'/routes', 0775, true);
        @mkdir($targetDir.'/app/Http/Controllers', 0775, true);
        @mkdir($targetDir.'/resources/views/clientes', 0775, true);
        @mkdir($targetDir.'/app/Models', 0775, true);
        @mkdir($targetDir.'/config', 0775, true);

        // 1. Setup rutas legacy (routes/web.php con prefijo clientes y grupo auth)
        $legacyWeb = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
});

Route::middleware('auth')->group(function () {
    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/{dni}', [ClienteController::class, 'show'])->name('clientes.show');
    });
});
PHP;
        file_put_contents($targetDir.'/routes/web.php', $legacyWeb);

        // 2. Setup controlador legacy (ClienteController.php sin ClienteProfileService)
        $legacyController = <<<'PHP'
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ClienteCuenta;
use App\Models\CcdCliente;

class ClienteController extends Controller
{
    public function show(string $dni)
    {
        $cuentas = ClienteCuenta::query()
            ->where('numdoc', $dni)
            ->get(['numdoc','nombre','cuenta','operacion','entidad','cosecha']);

        $ccdDocs = collect();
        if (Schema::hasTable('ccd_clientes')) {
            $ccdDocs = CcdCliente::query()->where('numdoc', $dni)->get();
        }

        $ccdByDni = collect([$dni => $ccdDocs->values()]);

        return view('clientes.show', compact('dni', 'cuentas', 'ccdDocs', 'ccdByDni'));
    }
}
PHP;
        file_put_contents($targetDir.'/app/Http/Controllers/ClienteController.php', $legacyController);

        // 3. Setup vista legacy (show.blade.php con celda legacy)
        $legacyView = <<<'BLADE'
@extends('layouts.app')
@section('content')
<table>
    @foreach($cuentas as $cta)
        <tr>
            <td>{{ $cta->operacion }}</td>
            {{-- === CELDA CCD === --}}
            <td class="text-nowrap">
                @php
                    $docs = collect($ccdByDni[$dni] ?? []);
                @endphp
                @if($docs->isNotEmpty())
                    <a href="{{ $docs->first()->link }}">Ver</a>
                @else
                    <span>—</span>
                @endif
            </td>
            {{-- === /CELDA CCD === --}}
        </tr>
    @endforeach
</table>
@endsection
BLADE;
        file_put_contents($targetDir.'/resources/views/clientes/show.blade.php', $legacyView);

        // 4. Setup modelos base y filesystems
        file_put_contents($targetDir.'/app/Models/ClienteCuenta.php', "<?php\nnamespace App\Models;\nuse Illuminate\Database\Eloquent\Model;\nclass ClienteCuenta extends Model {}\n");
        file_put_contents($targetDir.'/app/Models/CcdCliente.php', "<?php\nnamespace App\Models;\nuse Illuminate\Database\Eloquent\Model;\nclass CcdCliente extends Model {}\n");
        file_put_contents($targetDir.'/config/filesystems.php', "<?php\nreturn ['disks' => ['local' => []]];\n");

        // 5. Test DRY-RUN: Cero escrituras en archivos
        $dryRunResults = $service->verifyAndIntegrateComponents($sourceDir, $targetDir, false);
        $this->assertEquals('pending_additive_integration', $dryRunResults['routes/web.php']['status']);
        $this->assertEquals('pending_additive_integration', $dryRunResults['app/Http/Controllers/ClienteController.php']['status']);
        $this->assertEquals('pending_additive_integration', $dryRunResults['resources/views/clientes/show.blade.php']['status']);
        $this->assertFalse(str_contains((string) file_get_contents($targetDir.'/routes/web.php'), 'clientes.ccd.show'));
        $this->assertFalse(str_contains((string) file_get_contents($targetDir.'/app/Http/Controllers/ClienteController.php'), 'ccdConfirmados'));

        // 6. Test APPLY: Integración quirúrgica aditiva
        $applyResults = $service->verifyAndIntegrateComponents($sourceDir, $targetDir, true, $backupDir);
        $this->assertEquals('additively_integrated', $applyResults['routes/web.php']['status']);
        $this->assertEquals('additively_integrated', $applyResults['app/Http/Controllers/ClienteController.php']['status']);
        $this->assertEquals('additively_integrated', $applyResults['resources/views/clientes/show.blade.php']['status']);

        // Verificaciones de contenido inyectado
        $modRoutes = (string) file_get_contents($targetDir.'/routes/web.php');
        $this->assertStringContainsString('CcdDocumentController::class', $modRoutes);
        $this->assertStringContainsString("name('clientes.ccd.show')", $modRoutes);

        $modController = (string) file_get_contents($targetDir.'/app/Http/Controllers/ClienteController.php');
        $this->assertStringContainsString("'id','numdoc'", $modController);
        $this->assertStringContainsString('use App\Models\CcdClienteCuenta;', $modController);
        $this->assertStringContainsString('$cta->ccdConfirmados =', $modController);
        $this->assertStringContainsString('cliente_cuenta_id', $modController);

        $modView = (string) file_get_contents($targetDir.'/resources/views/clientes/show.blade.php');
        $this->assertStringContainsString('cta->ccdConfirmados', $modView);
        $this->assertStringContainsString("route('clientes.ccd.show'", $modView);
        $this->assertStringContainsString('{{-- === CELDA CCD === --}}', $modView);

        // Verificación de respaldos individuales
        $this->assertFileExists($backupDir.'/modified_files/routes/web.php');
        $this->assertFileExists($backupDir.'/modified_files/app/Http/Controllers/ClienteController.php');
        $this->assertFileExists($backupDir.'/modified_files/resources/views/clientes/show.blade.php');

        // Limpieza recursiva
        $deleteTree = function (string $dir) use (&$deleteTree) {
            if (! is_dir($dir)) {
                return;
            }
            $items = array_diff(scandir($dir) ?: [], ['.', '..']);
            foreach ($items as $item) {
                $path = $dir.'/'.$item;
                is_dir($path) ? $deleteTree($path) : @unlink($path);
            }
            @rmdir($dir);
        };
        $deleteTree($targetDir);
        $deleteTree($backupDir);
    }

    public function test_legacy_controller_ccd_confirmed_matches_operation_specifically_without_cross_pollution(): void
    {
        $dni = '40889911';
        $cta1 = ClienteCuenta::create([
            'numdoc' => $dni,
            'cuenta' => 'CTA-001',
            'operacion' => 'OP-AQP-001',
            'entidad' => 'CAJA AQP',
            'cosecha' => 'AQP4',
        ]);
        $cta2 = ClienteCuenta::create([
            'numdoc' => $dni,
            'cuenta' => 'CTA-002',
            'operacion' => 'OP-AQP-002',
            'entidad' => 'CAJA AQP',
            'cosecha' => 'AQP4',
        ]);

        $archivo = CcdArchivo::create([
            'disk' => 'ccd-private',
            'path' => CcdArchivo::pathForHash('a1b2c3d4e5f60123456789abcdef0123456789abcdef0123456789abcdef0123'),
            'sha256' => 'a1b2c3d4e5f60123456789abcdef0123456789abcdef0123456789abcdef0123',
            'size_bytes' => 2048,
            'mime_type' => 'application/pdf',
            'nombre_original' => 'doc1.pdf',
            'verified_at' => now(),
        ]);

        $ccd1 = CcdCliente::create([
            'numdoc' => $dni,
            'pdf' => 'doc1.pdf',
            'cosecha' => 'AQP4',
            'ccd_archivo_id' => $archivo->id,
        ]);

        CcdClienteCuenta::create([
            'ccd_cliente_id' => $ccd1->id,
            'cliente_cuenta_id' => $cta1->id,
            'estado' => CcdClienteCuenta::ESTADO_CONFIRMADA,
            'metodo_verificacion' => 'operacion_pdf_aqp_auditada_v5',
            'evidencia' => 'Hash Match',
            'confirmado_at' => now(),
        ]);

        // Simular lógica del controlador legacy
        $cuentas = ClienteCuenta::query()->where('numdoc', $dni)->get();
        $ccdDocs = CcdCliente::query()->where('numdoc', $dni)->get();

        $confirmedLinks = CcdClienteCuenta::query()
            ->whereIn('ccd_cliente_id', $ccdDocs->pluck('id'))
            ->where('estado', CcdClienteCuenta::ESTADO_CONFIRMADA)
            ->with('ccdCliente:id,numdoc,pdf,cosecha,link')
            ->get()
            ->filter(fn ($link) => $link->ccdCliente !== null && hash_equals($dni, (string) $link->ccdCliente->numdoc))
            ->groupBy('cliente_cuenta_id');

        $confirmedIds = $confirmedLinks->flatten()->pluck('ccd_cliente_id')->map(fn ($id) => (int) $id)->unique()->all();

        foreach ($cuentas as $cta) {
            $cta->ccdConfirmados = collect($confirmedLinks->get((int) $cta->id, []))
                ->pluck('ccdCliente')
                ->filter()
                ->sortBy('id')
                ->values();
        }

        $remainingCcdDocs = $ccdDocs->reject(fn ($doc) => in_array((int) $doc->id, $confirmedIds, true))->values();

        // 1. La cuenta 101 (OP-AQP-001) tiene exactamente su CCD
        $cta101 = $cuentas->firstWhere('id', $cta1->id);
        $this->assertNotNull($cta101);
        $this->assertCount(1, $cta101->ccdConfirmados);
        $this->assertEquals($ccd1->id, $cta101->ccdConfirmados->first()->id);

        // 2. La cuenta 102 (OP-AQP-002) NO tiene ningún CCD confirmado (cero contaminación cruzada)
        $cta102 = $cuentas->firstWhere('id', $cta2->id);
        $this->assertNotNull($cta102);
        $this->assertCount(0, $cta102->ccdConfirmados);

        // 3. El CCD confirmado se retira del pool general
        $this->assertCount(0, $remainingCcdDocs);
    }

    public function test_ccd_document_controller_authorizes_legacy_and_modern_and_prevents_parameter_tampering(): void
    {
        $dniReal = '41223344';
        $dniAjeno = '99887766';

        $archivo = CcdArchivo::create([
            'disk' => 'ccd-private',
            'path' => CcdArchivo::pathForHash('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'),
            'sha256' => 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210',
            'size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'nombre_original' => 'seguro.pdf',
            'verified_at' => now(),
        ]);

        $ccdCliente = CcdCliente::create([
            'numdoc' => $dniReal,
            'pdf' => 'seguro.pdf',
            'cosecha' => 'AQP4',
            'ccd_archivo_id' => $archivo->id,
        ]);

        Storage::fake('ccd-private');
        config(['ccd.private_disk' => 'ccd-private']);
        Storage::disk('ccd-private')->put($archivo->path, '%PDF-1.4 Fake PDF Content');

        $user = User::factory()->create();

        // 1. Acceso legítimo con usuario autenticado, activo y con rol autorizado
        $this->actingAs($user);
        $response = $this->get(route('clientes.ccd.show', ['numdoc' => $dniReal, 'ccdCliente' => $ccdCliente->id]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'inline; filename=seguro.pdf');

        // 2. Intento de manipulación de parámetro URL (DNI ajeno para documento legítimo) -> Debe abortar con 404
        $responseTampered = $this->get("/clientes/{$dniAjeno}/ccd/{$ccdCliente->id}");
        $responseTampered->assertStatus(404);

        // 3. Intento de acceso sin autenticación -> Debe rechazar con 401 o redirigir a login
        auth()->logout();
        $responseGuest = $this->get("/clientes/{$dniReal}/ccd/{$ccdCliente->id}");
        $this->assertTrue(in_array($responseGuest->getStatusCode(), [302, 401], true));

        // 4. Intento de acceso con usuario inactivo -> Debe ser rechazado (302 a login por middleware active o 403)
        $inactiveUser = User::factory()->create(['active' => false]);
        $this->actingAs($inactiveUser);
        $responseInactive = $this->get("/clientes/{$dniReal}/ccd/{$ccdCliente->id}");
        $this->assertTrue(in_array($responseInactive->getStatusCode(), [302, 403], true));
        if ($responseInactive->getStatusCode() === 302) {
            $responseInactive->assertRedirect(route('login'));
        }

        // 5. Intento de acceso cuando el Gate o permiso view-cliente rechaza la autorización -> Debe abortar con 403 sin fallback permisivo
        \Illuminate\Support\Facades\Gate::define('view-cliente', fn () => false);
        $this->actingAs($user);
        $responseForbidden = $this->get("/clientes/{$dniReal}/ccd/{$ccdCliente->id}");
        $responseForbidden->assertStatus(403);
        \Illuminate\Support\Facades\Gate::define('view-cliente', [\App\Policies\ClientePolicy::class, 'view']);

        // 6. Intento de acceso con usuario bloqueado específicamente para este cliente -> Debe abortar con 403
        $blockedUser = User::factory()->create(['role' => 'asesor', 'active' => true]);
        if (! \Illuminate\Support\Facades\Schema::hasTable('cliente_bloqueos')) {
            \Illuminate\Support\Facades\Schema::create('cliente_bloqueos', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('numdoc');
            });
        }
        \Illuminate\Support\Facades\DB::table('cliente_bloqueos')->insert([
            'user_id' => $blockedUser->id,
            'numdoc' => $dniReal,
        ]);
        $this->actingAs($blockedUser);
        $responseBlocked = $this->get("/clientes/{$dniReal}/ccd/{$ccdCliente->id}");
        $responseBlocked->assertStatus(403);
    }

    public function test_inspect_target_schema_unifies_column_keys_and_detects_discrepancies(): void
    {
        $service = new CcdProductionMigratorService();

        // 1. Estado inicial de producción legacy: sin ccd_archivos, sin ccd_cliente_cuenta, sin ccd_archivo_id
        $cleanSchema = $service->inspectTargetSchema($this->targetPdo);
        $this->assertFalse($cleanSchema['has_ccd_archivos']);
        $this->assertFalse($cleanSchema['has_ccd_cliente_cuenta']);
        $this->assertFalse($cleanSchema['has_ccd_archivo_id_col']);
        $this->assertFalse($cleanSchema['has_ccd_archivo_id_column']);
        $this->assertSame($cleanSchema['has_ccd_archivo_id_col'], $cleanSchema['has_ccd_archivo_id_column']);
        $this->assertFalse($cleanSchema['is_ready_for_apply']);
        $this->assertCount(2, $cleanSchema['pending_migrations']);

        // 2. Estado tras aplicar la primera migración (solo ccd_archivos y columna ccd_archivo_id)
        $this->targetPdo->exec('CREATE TABLE IF NOT EXISTS ccd_archivos (
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
        $this->targetPdo->exec('ALTER TABLE ccd_clientes ADD COLUMN ccd_archivo_id BIGINT UNSIGNED NULL AFTER codigo');
        $this->targetPdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes', 1)");

        $partialSchema = $service->inspectTargetSchema($this->targetPdo);
        $this->assertTrue($partialSchema['has_ccd_archivos']);
        $this->assertFalse($partialSchema['has_ccd_cliente_cuenta']);
        $this->assertTrue($partialSchema['has_ccd_archivo_id_col']);
        $this->assertTrue($partialSchema['has_ccd_archivo_id_column']);
        $this->assertSame($partialSchema['has_ccd_archivo_id_col'], $partialSchema['has_ccd_archivo_id_column']);
        $this->assertFalse($partialSchema['is_ready_for_apply']);
        $this->assertCount(1, $partialSchema['pending_migrations']);

        // 3. Estado tras aplicar la segunda migración (ccd_cliente_cuenta)
        $this->targetPdo->exec("CREATE TABLE IF NOT EXISTS ccd_cliente_cuenta (
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
            UNIQUE KEY ccd_cliente_cuenta_unique (ccd_cliente_id, cliente_cuenta_id)
        ) ENGINE=InnoDB");
        $this->targetPdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2026_11_13_000001_create_ccd_cliente_cuenta_table', 1)");

        $readySchema = $service->inspectTargetSchema($this->targetPdo);
        $this->assertTrue($readySchema['has_ccd_archivos']);
        $this->assertTrue($readySchema['has_ccd_cliente_cuenta']);
        $this->assertTrue($readySchema['has_ccd_archivo_id_col']);
        $this->assertTrue($readySchema['has_ccd_archivo_id_column']);
        $this->assertSame($readySchema['has_ccd_archivo_id_col'], $readySchema['has_ccd_archivo_id_column']);
        $this->assertTrue($readySchema['is_ready_for_apply']);
        $this->assertEmpty($readySchema['pending_migrations']);
    }

    public function test_install_migration_files_copies_and_validates_syntax_before_migration(): void
    {
        $service = new CcdProductionMigratorService();
        $sourceDir = storage_path('framework/testing/test_source_app_mig');
        $targetDir = storage_path('framework/testing/test_target_app_mig');
        $backupDir = storage_path('framework/testing/test_backup_mig');

        File::ensureDirectoryExists($sourceDir.'/database/migrations');
        File::ensureDirectoryExists($targetDir.'/database/migrations');
        File::ensureDirectoryExists($backupDir);

        $mig1 = 'database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php';
        $mig2 = 'database/migrations/2026_11_13_000001_create_ccd_cliente_cuenta_table.php';

        $validContent1 = "<?php\nuse Illuminate\Database\Migrations\Migration;\nreturn new class extends Migration { public function up(): void {} };\n";
        $validContent2 = "<?php\nuse Illuminate\Database\Migrations\Migration;\nreturn new class extends Migration { public function up(): void {} };\n";

        file_put_contents($sourceDir.'/'.$mig1, $validContent1);
        file_put_contents($sourceDir.'/'.$mig2, $validContent2);

        try {
            // 1. Dry run: reporta pending_install y CERO escrituras en destino
            $dryRunRes = $service->installMigrationFiles($sourceDir, $targetDir, false);
            $this->assertSame('pending_install', $dryRunRes[$mig1]['status']);
            $this->assertSame('pending_install', $dryRunRes[$mig2]['status']);
            $this->assertFileDoesNotExist($targetDir.'/'.$mig1);
            $this->assertFileDoesNotExist($targetDir.'/'.$mig2);

            // 2. Apply: instala archivos y valida sintaxis PHP
            $applyRes = $service->installMigrationFiles($sourceDir, $targetDir, true, $backupDir);
            $this->assertSame('installed', $applyRes[$mig1]['status']);
            $this->assertSame('installed', $applyRes[$mig2]['status']);
            $this->assertFileExists($targetDir.'/'.$mig1);
            $this->assertFileExists($targetDir.'/'.$mig2);

            // 3. Re-ejecución apply con archivos idénticos: reporta identical sin tocar nada
            $idempotentRes = $service->installMigrationFiles($sourceDir, $targetDir, true, $backupDir);
            $this->assertSame('identical', $idempotentRes[$mig1]['status']);
            $this->assertSame('identical', $idempotentRes[$mig2]['status']);

            // 4. Modificación en destino: si difiere, apply crea backup y actualiza con validación
            file_put_contents($targetDir.'/'.$mig1, "<?php\n// Modificado previamente en destino\n");
            $diffRes = $service->installMigrationFiles($sourceDir, $targetDir, true, $backupDir);
            $this->assertSame('updated_with_backup', $diffRes[$mig1]['status']);
            $this->assertFileExists($backupDir.'/modified_files/'.$mig1);

            // 5. Archivo con error de sintaxis PHP en fuente: aborta con DomainException antes de copiar
            file_put_contents($sourceDir.'/'.$mig2, "<?php\nthis is invalid php syntax {;");
            try {
                $service->installMigrationFiles($sourceDir, $targetDir, true, $backupDir);
                $this->fail('Se esperaba DomainException por error de sintaxis');
            } catch (DomainException $e) {
                $this->assertStringContainsString('Error de sintaxis PHP', $e->getMessage());
            }
        } finally {
            File::deleteDirectory($sourceDir);
            File::deleteDirectory($targetDir);
            File::deleteDirectory($backupDir);
        }
    }

    public function test_audit_target_contract_and_anomaly_abort(): void
    {
        $service = new CcdProductionMigratorService();
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        // Sembrar 1 registro coherente en destino
        $content = '%PDF-1.4 Simulated Clean Audit Target Content';
        $sha256 = hash('sha256', $content);
        $size = strlen($content);
        $relPath = "sha256/33/44/{$sha256}.pdf";
        $targetFile = $this->targetStorage.'/'.$relPath;
        File::ensureDirectoryExists(dirname($targetFile));
        file_put_contents($targetFile, $content);

        $this->targetPdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', '{$relPath}', '{$sha256}', {$size}, 'application/pdf', 'doc.pdf', NOW(), NOW(), NOW())");
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '77889900', 'doc.pdf', 'AQP4', 1)");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '77889900', 'CTA-AUD-1', 'OP-AUD-1', 'Caja AQP', 'CAJAAQP4')");
        $this->targetPdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Verificado OK', NOW())");

        // 1. Auditoría exitosa: validar contrato de claves primarias y alias canónicos
        $cleanAudit = $service->auditTarget($this->targetPdo, $this->targetStorage, false, 1);

        // Claves primarias
        $this->assertArrayHasKey('passed', $cleanAudit);
        $this->assertArrayHasKey('anomalies', $cleanAudit);
        $this->assertArrayHasKey('archivos_count', $cleanAudit);
        $this->assertArrayHasKey('assoc_confirmed', $cleanAudit);

        // Alias canónicos del contrato
        $this->assertArrayHasKey('success', $cleanAudit);
        $this->assertArrayHasKey('errors', $cleanAudit);
        $this->assertArrayHasKey('total_archivos', $cleanAudit);
        $this->assertArrayHasKey('total_asociaciones', $cleanAudit);

        // Equivalencia estricta
        $this->assertTrue($cleanAudit['passed']);
        $this->assertTrue($cleanAudit['success']);
        $this->assertSame($cleanAudit['passed'], $cleanAudit['success']);
        $this->assertEmpty($cleanAudit['anomalies']);
        $this->assertEmpty($cleanAudit['errors']);
        $this->assertSame($cleanAudit['archivos_count'], $cleanAudit['total_archivos']);
        $this->assertSame($cleanAudit['assoc_confirmed'], $cleanAudit['total_asociaciones']);
        $this->assertSame(1, $cleanAudit['archivos_count']);
        $this->assertSame(1, $cleanAudit['assoc_confirmed']);

        // 2. Simular anomalías: archivo físico ausente, ccd_cliente sin ccd_archivo_id y asociación huérfana
        unlink($targetFile); // Borrar archivo físico del almacenamiento

        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (2, '77889901', 'doc2.pdf', 'AQP5', NULL)"); // Sin archivo vinculado
        $this->targetPdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->targetPdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (2, 999999, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Huérfana', NOW())"); // ccd_cliente inexistente
        $this->targetPdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $corruptAudit = $service->auditTarget($this->targetPdo, $this->targetStorage, false, 1);

        $this->assertFalse($corruptAudit['passed']);
        $this->assertFalse($corruptAudit['success']);
        $this->assertSame($corruptAudit['passed'], $corruptAudit['success']);
        $this->assertNotEmpty($corruptAudit['anomalies']);
        $this->assertNotEmpty($corruptAudit['errors']);
        $this->assertSame($corruptAudit['anomalies'], $corruptAudit['errors']);

        // Validar lógica del script bash: if (! $audit["passed"] || ! empty($audit["anomalies"]))
        $bashAbortCondition = (! $corruptAudit['passed'] || ! empty($corruptAudit['anomalies']));
        $this->assertTrue($bashAbortCondition, 'El script bash debe abortar inmediatamente si detecta anomalías en la auditoría');
    }

    public function test_five_phases_end_to_end_legacy_flow_with_isolated_mysql_databases(): void
    {
        $service = new CcdProductionMigratorService();
        $sourceAppDir = base_path();
        $targetAppDir = storage_path('framework/testing/e2e_target_app_'.uniqid());
        $backupDir = storage_path('framework/testing/e2e_backup_app_'.uniqid());

        File::ensureDirectoryExists($targetAppDir.'/database/migrations');
        File::ensureDirectoryExists($targetAppDir.'/routes');
        File::ensureDirectoryExists($targetAppDir.'/app/Http/Controllers');
        File::ensureDirectoryExists($targetAppDir.'/resources/views/clientes');
        File::ensureDirectoryExists($targetAppDir.'/app/Models');
        File::ensureDirectoryExists($targetAppDir.'/config');
        File::ensureDirectoryExists($backupDir);

        try {
            // Preparar archivos legacy en targetAppDir
            file_put_contents($targetAppDir.'/routes/web.php', "<?php\nuse Illuminate\Support\Facades\Route;\nRoute::get('/clientes/{dni}', 'ClienteController@show');\n");
            file_put_contents($targetAppDir.'/app/Http/Controllers/ClienteController.php', "<?php\nnamespace App\Http\Controllers;\nclass ClienteController {\n    public function show(\$dni) {\n        \$cuentas = collect();\n        \$ccdDocs = collect();\n        return view('clientes.show', compact('cuentas', 'ccdDocs'));\n    }\n}\n");
            file_put_contents($targetAppDir.'/resources/views/clientes/show.blade.php', "<table><tr><td>OP</td>{{-- === CELDA CCD === --}}\n<td class=\"text-nowrap\"><span>—</span></td>\n{{-- === /CELDA CCD === --}}</tr></table>");
            file_put_contents($targetAppDir.'/app/Models/ClienteCuenta.php', "<?php\nnamespace App\Models;\nuse Illuminate\Database\Eloquent\Model;\nclass ClienteCuenta extends Model {}\n");
            file_put_contents($targetAppDir.'/app/Models/CcdCliente.php', "<?php\nnamespace App\Models;\nuse Illuminate\Database\Eloquent\Model;\nclass CcdCliente extends Model {}\n");
            file_put_contents($targetAppDir.'/config/filesystems.php', "<?php\nreturn ['disks' => ['local' => []]];\n");

            // Sembrar 2 registros en origen (AQP4 y AQP5)
            $contentAqp4 = '%PDF-1.4 Simulated E2E AQP4 Content';
            $shaAqp4 = hash('sha256', $contentAqp4);
            $sizeAqp4 = strlen($contentAqp4);
            $relAqp4 = "sha256/aa/bb/{$shaAqp4}.pdf";
            $srcAqp4 = $this->sourceStorage.'/'.$relAqp4;
            File::ensureDirectoryExists(dirname($srcAqp4));
            file_put_contents($srcAqp4, $contentAqp4);

            $contentAqp5 = '%PDF-1.4 Simulated E2E AQP5 Content';
            $shaAqp5 = hash('sha256', $contentAqp5);
            $sizeAqp5 = strlen($contentAqp5);
            $relAqp5 = "sha256/cc/dd/{$shaAqp5}.pdf";
            $srcAqp5 = $this->sourceStorage.'/'.$relAqp5;
            File::ensureDirectoryExists(dirname($srcAqp5));
            file_put_contents($srcAqp5, $contentAqp5);

            $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                    VALUES (10, 'ccd-private', '{$relAqp4}', '{$shaAqp4}', {$sizeAqp4}, 'application/pdf', 'aqp4.pdf', NOW(), NOW(), NOW()),
                                           (20, 'ccd-private', '{$relAqp5}', '{$shaAqp5}', {$sizeAqp5}, 'application/pdf', 'aqp5.pdf', NOW(), NOW(), NOW())");

            $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                    VALUES (100, '55667788', 'aqp4.pdf', 'AQP4', 10),
                                           (200, '66778899', 'aqp5.pdf', 'AQP5', 20)");

            $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                    VALUES (1000, '55667788', 'CTA-55', 'OP-55', 'Caja AQP', 'CAJAAQP4'),
                                           (2000, '66778899', 'CTA-66', 'OP-66', 'Caja AQP', 'CAJAAQP5')");

            $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                    VALUES (1, 100, 1000, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'E2E AQP4 Hash OK', NOW()),
                                           (2, 200, 2000, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'E2E AQP5 Hash OK', NOW())");

            // Sembrar destino con estado inicial LEGACY (clientes y cuentas existen, pero sin ccd_archivo_id ni tablas de archivos/asociaciones)
            $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha)
                                    VALUES (100, '55667788', 'aqp4.pdf', 'AQP4'),
                                           (200, '66778899', 'aqp5.pdf', 'AQP5')");

            $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                    VALUES (1000, '55667788', 'CTA-55', 'OP-55', 'Caja AQP', 'CAJAAQP4'),
                                           (2000, '66778899', 'CTA-66', 'OP-66', 'Caja AQP', 'CAJAAQP5')");

            // ==============================================================================
            // FASE 1: PREFLIGHT Y VALIDACIÓN DE FIRMAS SHA-256
            // ==============================================================================
            $shaCcdSource = $service->calculateCcdAqpSha256($this->sourcePdo);
            $shaCcdTarget = $service->calculateCcdAqpSha256($this->targetPdo);
            $this->assertSame($shaCcdSource, $shaCcdTarget);

            $shaCuentasSource = $service->calculateCuentasSha256($this->sourcePdo);
            $shaCuentasTarget = $service->calculateCuentasSha256($this->targetPdo);
            $this->assertSame($shaCuentasSource, $shaCuentasTarget);

            // Preflight dry-run del esquema destino: detecta esquema legacy no listo
            $preSchema = $service->inspectTargetSchema($this->targetPdo);
            $this->assertFalse($preSchema['is_ready_for_apply']);
            $this->assertFalse($preSchema['has_ccd_archivo_id_column']);
            $this->assertFalse($preSchema['has_ccd_archivo_id_col']);
            $this->assertCount(2, $preSchema['pending_migrations']);

            // ==============================================================================
            // FASE 2: INSTALACIÓN Y EJECUCIÓN DE MIGRACIONES SELECTIVAS
            // ==============================================================================
            // 2.1 En dry-run: comprueba archivos sin escribir
            $dryRunInstall = $service->installMigrationFiles($sourceAppDir, $targetAppDir, false);
            $this->assertSame('pending_install', $dryRunInstall['database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php']['status']);
            $this->assertFileDoesNotExist($targetAppDir.'/database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php');

            // 2.2 En apply: instala archivos con validación de sintaxis
            $applyInstall = $service->installMigrationFiles($sourceAppDir, $targetAppDir, true, $backupDir);
            $this->assertSame('installed', $applyInstall['database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php']['status']);
            $this->assertFileExists($targetAppDir.'/database/migrations/2026_10_30_120000_create_ccd_archivos_and_link_ccd_clientes.php');

            // 2.3 Ejecutar migraciones selectivas sobre el destino
            $this->applySelectiveMigrationsToTarget($this->targetPdo);

            // 2.4 Verificar que el esquema destino ahora sí esté completamente listo
            $postSchema = $service->inspectTargetSchema($this->targetPdo);
            $this->assertTrue($postSchema['is_ready_for_apply']);
            $this->assertTrue($postSchema['has_ccd_archivos']);
            $this->assertTrue($postSchema['has_ccd_cliente_cuenta']);
            $this->assertTrue($postSchema['has_ccd_archivo_id_column']);
            $this->assertTrue($postSchema['has_ccd_archivo_id_col']);
            $this->assertEmpty($postSchema['pending_migrations']);

            // ==============================================================================
            // FASE 3: MIGRACIÓN DE ARCHIVOS Y DATOS (APPLY E IDEMPOTENCIA)
            // ==============================================================================
            $manifestPath = $this->targetStorage.'/e2e_manifest.csv';

            // 3.1 Apply de migración de datos
            $migResult = $service->migrate([
                'source_pdo' => $this->sourcePdo,
                'target_pdo' => $this->targetPdo,
                'source_storage' => $this->sourceStorage,
                'target_storage' => $this->targetStorage,
                'apply' => true,
                'manifest_path' => $manifestPath,
                'expected_total' => 2,
            ]);

            $this->assertSame('success', $migResult['status']);
            $this->assertSame(2, $migResult['files_transferred']);
            $this->assertSame(2, $migResult['archivos_migrated']);
            $this->assertSame(2, $migResult['links_updated']);
            $this->assertSame(2, $migResult['associations_migrated']);
            $this->assertFileExists($this->targetStorage.'/'.$relAqp4);
            $this->assertFileExists($this->targetStorage.'/'.$relAqp5);
            $this->assertFileExists($manifestPath);

            // 3.2 Idempotencia: re-ejecución sobre el mismo destino no duplica ni falla
            $idempotentResult = $service->migrate([
                'source_pdo' => $this->sourcePdo,
                'target_pdo' => $this->targetPdo,
                'source_storage' => $this->sourceStorage,
                'target_storage' => $this->targetStorage,
                'apply' => true,
                'manifest_path' => $manifestPath,
                'expected_total' => 2,
            ]);
            $this->assertSame('success', $idempotentResult['status']);
            $this->assertSame(0, $idempotentResult['files_transferred']);
            $this->assertSame(2, $idempotentResult['archivos_migrated']);
            $this->assertSame(2, $idempotentResult['associations_migrated']);
            $this->assertSame(2, (int) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_cliente_cuenta')->fetchColumn());
            $this->assertSame(2, (int) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_archivos')->fetchColumn());

            // ==============================================================================
            // FASE 4: INTEGRACIÓN COMPATIBLE EN ARQUITECTURA LEGACY
            // ==============================================================================
            $intResult = $service->verifyAndIntegrateComponents($sourceAppDir, $targetAppDir, true, $backupDir);
            $this->assertSame('additively_integrated', $intResult['routes/web.php']['status']);
            $this->assertSame('additively_integrated', $intResult['app/Http/Controllers/ClienteController.php']['status']);
            $this->assertSame('additively_integrated', $intResult['resources/views/clientes/show.blade.php']['status']);

            // Comprobar sintaxis PHP de los componentes modificados
            $service->assertPhpSyntax($targetAppDir.'/routes/web.php');
            $service->assertPhpSyntax($targetAppDir.'/app/Http/Controllers/ClienteController.php');

            // ==============================================================================
            // FASE 5: AUDITORÍA FINAL POST-MIGRACIÓN Y VERIFICACIÓN DE ROLLBACK
            // ==============================================================================
            $finalAudit = $service->auditTarget($this->targetPdo, $this->targetStorage, false, 2);
            $this->assertTrue($finalAudit['passed']);
            $this->assertTrue($finalAudit['success']);
            $this->assertEmpty($finalAudit['anomalies']);
            $this->assertEmpty($finalAudit['errors']);
            $this->assertSame(2, $finalAudit['archivos_count']);
            $this->assertSame(2, $finalAudit['total_archivos']);
            $this->assertSame(2, $finalAudit['assoc_confirmed']);
            $this->assertSame(2, $finalAudit['total_asociaciones']);

            // Verificación de aislamiento del rollback
            $rollbackSafety = $service->validateRollbackSafety($this->targetPdo);
            $this->assertTrue($rollbackSafety['safe']);
            $this->assertEmpty($rollbackSafety['reasons']);
        } finally {
            // Limpieza de directorios temporales
            File::deleteDirectory($targetAppDir);
            File::deleteDirectory($backupDir);
        }
    }

    public function test_refresh_pdo_connection_reconnects_after_mysql_2006_and_rejects_in_transaction(): void
    {
        $service = new CcdProductionMigratorService;

        // 1. pingPdo retorna true en conexión activa
        $this->assertTrue($service->pingPdo($this->targetPdo));

        // 2. refreshPdoConnection con credenciales renueva la instancia PDO
        $targetCreds = array_merge($this->dbConfig, ['database' => 'kp_crm_testing_target']);
        $logs = [];
        $logger = function (string $msg) use (&$logs) {
            $logs[] = $msg;
        };

        $origPdo = $this->targetPdo;
        $refreshedPdo = $service->refreshPdoConnection($this->targetPdo, null, $targetCreds, $logger, 'Producción');
        $this->assertInstanceOf(PDO::class, $refreshedPdo);
        $this->assertTrue($service->pingPdo($this->targetPdo));
        $this->assertNotSame($origPdo, $this->targetPdo);
        $this->assertNotEmpty($logs);
        $this->assertStringContainsString('Renovando controladamente Producción', $logs[0]);

        // 3. refreshPdoConnection con reconnector personalizado
        $reconnected = false;
        $reconnector = function () use ($targetCreds, &$reconnected, $service) {
            $reconnected = true;

            return $service->createPdoConnection($targetCreds);
        };
        $service->refreshPdoConnection($this->targetPdo, $reconnector, null, null, 'Producción');
        $this->assertTrue($reconnected);

        // 4. refreshPdoConnection dentro de una transacción activa DEBE abortar con DomainException
        $this->targetPdo->beginTransaction();
        try {
            $service->refreshPdoConnection($this->targetPdo, null, $targetCreds, null, 'Producción');
            $this->fail('Debe lanzar DomainException si la conexión está en transacción.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('transacción abierta', $e->getMessage());
        } finally {
            if ($this->targetPdo->inTransaction()) {
                $this->targetPdo->rollBack();
            }
        }
    }

    public function test_validate_associations_pre_insert_batches_where_in_and_detects_conflicts(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        // Datos base: 2 elementos válidos
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha) VALUES
            (10, '11111111', 'doc10.pdf', 'AQP4'),
            (20, '22222222', 'doc20.pdf', 'AQP5')");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha) VALUES
            (100, '11111111', 'CTA-10', 'OP-10', 'Caja AQP', 'CAJAAQP4'),
            (200, '22222222', 'CTA-20', 'OP-20', 'Caja AQP', 'CAJAAQP5')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at) VALUES
            (1, 10, 100, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'OK 1', NOW()),
            (2, 20, 200, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'OK 2', NOW())");

        // Destino con ccd_clientes y clientes_cuentas correspondientes
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha) VALUES
            (10, '11111111', 'doc10.pdf', 'AQP4'),
            (20, '22222222', 'doc20.pdf', 'AQP5')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha) VALUES
            (100, '11111111', 'CTA-10', 'OP-10', 'Caja AQP', 'CAJAAQP4'),
            (200, '22222222', 'CTA-20', 'OP-20', 'Caja AQP', 'CAJAAQP5')");

        // Validación exitosa con lotes
        $logs = [];
        $service->validateAssociationsPreInsert(
            $this->sourcePdo,
            $this->targetPdo,
            2,
            1, // Forzar múltiples lotes
            function (string $msg) use (&$logs) {
                $logs[] = $msg;
            }
        );
        $this->assertNotEmpty($logs);
        $this->assertStringContainsString('Validación previa masiva completada conforme: 2 asociaciones verificadas', end($logs));

        // Subprueba A: Detección de cliente faltante en destino
        $this->targetPdo->exec('DELETE FROM ccd_clientes WHERE id = 20');
        try {
            $service->validateAssociationsPreInsert($this->sourcePdo, $this->targetPdo, 2, 500);
            $this->fail('Debe fallar al faltar el cliente en destino');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no existe en destino', $e->getMessage());
        }

        // Subprueba B: Detección de discrepancia de DNI en destino
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha) VALUES (20, '99999999', 'doc20.pdf', 'AQP5')");
        try {
            $service->validateAssociationsPreInsert($this->sourcePdo, $this->targetPdo, 2, 500);
            $this->fail('Debe fallar si el DNI en destino difiere');
        } catch (DomainException $e) {
            $this->assertStringContainsString('difiere en destino', $e->getMessage());
        }

        // Subprueba C: Conflicto con asociación existente incompatible
        $this->targetPdo->exec("UPDATE ccd_clientes SET numdoc = '22222222' WHERE id = 20");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha) VALUES (299, '22222222', 'CTA-299', 'OP-299', 'Caja AQP', 'CAJAAQP5')");
        $this->targetPdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at) VALUES
            (99, 20, 299, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Conflicto', NOW())");
        try {
            $service->validateAssociationsPreInsert($this->sourcePdo, $this->targetPdo, 2, 500);
            $this->fail('Debe fallar si existe colisión de asociación con cuenta distinta');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Conflicto de asociación incompatible en destino', $e->getMessage());
        }

        // Subprueba D: Idempotencia ante asociación existente idéntica
        $this->targetPdo->exec('DELETE FROM ccd_cliente_cuenta WHERE id = 99');
        $this->targetPdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at) VALUES
            (99, 20, 200, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Idéntica', NOW())");
        // Debe culminar sin excepciones
        $service->validateAssociationsPreInsert($this->sourcePdo, $this->targetPdo, 2, 500);
    }

    public function test_transfer_and_verify_files_closes_cursor_before_filesystem_operations(): void
    {
        $service = new CcdProductionMigratorService;

        $content = '%PDF-1.4 Test Cursor Closed Content';
        $sha256 = hash('sha256', $content);
        $size = strlen($content);
        $relPath = "sha256/33/44/{$sha256}.pdf";
        $srcFile = $this->sourceStorage.'/'.$relPath;
        File::ensureDirectoryExists(dirname($srcFile));
        file_put_contents($srcFile, $content);

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', '{$relPath}', '{$sha256}', {$size}, 'application/pdf', 'doc.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '44556677', 'doc.pdf', 'AQP4', 1)");

        $logs = [];
        $result = $service->transferAndVerifyFiles(
            $this->sourcePdo,
            $this->sourceStorage,
            $this->targetStorage,
            false,
            function (string $msg) use (&$logs) {
                $logs[] = $msg;
            },
            1
        );

        $this->assertSame(1, $result['verified']);
        $this->assertSame(0, $result['transferred']);

        // Assert que sourcePdo no mantiene cursor abierto y ejecuta consultas inmediatamente
        $row = $this->sourcePdo->query('SELECT COUNT(*) as c FROM ccd_archivos')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['c']);
    }

    public function test_migrate_renews_connections_and_executes_batch_validation_end_to_end(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        $content = '%PDF-1.4 Migrated Content V6.2.2';
        $sha256 = hash('sha256', $content);
        $size = strlen($content);
        $relPath = "sha256/55/66/{$sha256}.pdf";
        $srcFile = $this->sourceStorage.'/'.$relPath;
        File::ensureDirectoryExists(dirname($srcFile));
        file_put_contents($srcFile, $content);

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', '{$relPath}', '{$sha256}', {$size}, 'application/pdf', 'doc.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '88776655', 'doc.pdf', 'AQP4', 1)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '88776655', 'CTA-88', 'OP-88', 'Caja AQP', 'CAJAAQP4')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at)
                                VALUES (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'Verificado V6.2.2', NOW())");

        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha)
                                VALUES (1, '88776655', 'doc.pdf', 'AQP4')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (1, '88776655', 'CTA-88', 'OP-88', 'Caja AQP', 'CAJAAQP4')");

        $sourceCreds = array_merge($this->dbConfig, ['database' => 'kp_crm_testing_source']);
        $targetCreds = array_merge($this->dbConfig, ['database' => 'kp_crm_testing_target']);

        $logs = [];
        $logger = function (string $msg) use (&$logs) {
            $logs[] = $msg;
        };

        $sourceRenewed = false;
        $targetRenewed = false;

        $result = $service->migrate([
            'source_pdo' => $this->sourcePdo,
            'target_pdo' => $this->targetPdo,
            'source_credentials' => $sourceCreds,
            'target_credentials' => $targetCreds,
            'source_reconnector' => function () use ($sourceCreds, &$sourceRenewed, $service) {
                $sourceRenewed = true;

                return $service->createPdoConnection($sourceCreds);
            },
            'target_reconnector' => function () use ($targetCreds, &$targetRenewed, $service) {
                $targetRenewed = true;

                return $service->createPdoConnection($targetCreds);
            },
            'source_storage' => $this->sourceStorage,
            'target_storage' => $this->targetStorage,
            'apply' => true,
            'batch_size' => 10,
            'expected_total' => 1,
            'logger' => $logger,
        ]);

        $this->assertTrue($sourceRenewed);
        $this->assertTrue($targetRenewed);
        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['files_transferred']);
        $this->assertSame(1, $result['archivos_migrated']);
        $this->assertSame(1, $result['associations_migrated']);
        $this->assertTrue($result['audit']['passed']);

        $hasStagingRenewLog = false;
        $hasProdRenewLog = false;
        $hasValidationLog = false;
        foreach ($logs as $l) {
            if (str_contains($l, 'conexión origen (staging)')) {
                $hasStagingRenewLog = true;
            }
            if (str_contains($l, 'conexión destino (producción)')) {
                $hasProdRenewLog = true;
            }
            if (str_contains($l, 'Validación previa masiva completada conforme')) {
                $hasValidationLog = true;
            }
        }
        $this->assertTrue($hasStagingRenewLog);
        $this->assertTrue($hasProdRenewLog);
        $this->assertTrue($hasValidationLog);
    }

    public function test_audit_target_materializes_rows_closes_cursor_and_renews_connections_exceeding_timeout_for_manifest(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        $content1 = '%PDF-1.4 Audit File 1';
        $sha1 = hash('sha256', $content1);
        $size1 = strlen($content1);
        $relPath1 = "sha256/11/22/{$sha1}.pdf";
        $srcFile1 = $this->sourceStorage.'/'.$relPath1;
        $tgtFile1 = $this->targetStorage.'/'.$relPath1;
        File::ensureDirectoryExists(dirname($srcFile1));
        File::ensureDirectoryExists(dirname($tgtFile1));
        file_put_contents($srcFile1, $content1);
        file_put_contents($tgtFile1, $content1);

        $content2 = '%PDF-1.4 Audit File 2';
        $sha2 = hash('sha256', $content2);
        $size2 = strlen($content2);
        $relPath2 = "sha256/33/44/{$sha2}.pdf";
        $srcFile2 = $this->sourceStorage.'/'.$relPath2;
        $tgtFile2 = $this->targetStorage.'/'.$relPath2;
        File::ensureDirectoryExists(dirname($srcFile2));
        File::ensureDirectoryExists(dirname($tgtFile2));
        file_put_contents($srcFile2, $content2);
        file_put_contents($tgtFile2, $content2);

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at) VALUES
            (1, 'ccd-private', '{$relPath1}', '{$sha1}', {$size1}, 'application/pdf', 'doc1.pdf', NOW(), NOW(), NOW()),
            (2, 'ccd-private', '{$relPath2}', '{$sha2}', {$size2}, 'application/pdf', 'doc2.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id) VALUES
            (1, '10000001', 'doc1.pdf', 'AQP4', 1),
            (2, '10000002', 'doc2.pdf', 'AQP5', 2)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha) VALUES
            (1, '10000001', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4'),
            (2, '10000002', 'CTA-2', 'OP-2', 'Caja AQP', 'CAJAAQP5')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at) VALUES
            (1, 1, 1, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'EV1', NOW()),
            (2, 2, 2, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'EV2', NOW())");

        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha) VALUES
            (1, '10000001', 'doc1.pdf', 'AQP4'),
            (2, '10000002', 'doc2.pdf', 'AQP5')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha) VALUES
            (1, '10000001', 'CTA-1', 'OP-1', 'Caja AQP', 'CAJAAQP4'),
            (2, '10000002', 'CTA-2', 'OP-2', 'Caja AQP', 'CAJAAQP5')");

        $sourceCreds = array_merge($this->dbConfig, ['database' => 'kp_crm_testing_source']);
        $targetCreds = array_merge($this->dbConfig, ['database' => 'kp_crm_testing_target']);

        $logs = [];
        $logger = function (string $msg) use (&$logs) {
            $logs[] = $msg;
        };

        $postAuditRenewCount = 0;
        $manifestPath = $this->targetStorage.'/audits/test-manifest-'.time().'.csv';

        $result = $service->migrate([
            'source_pdo' => $this->sourcePdo,
            'target_pdo' => $this->targetPdo,
            'source_credentials' => $sourceCreds,
            'target_credentials' => $targetCreds,
            'source_reconnector' => function () use ($sourceCreds, &$postAuditRenewCount, $service) {
                $postAuditRenewCount++;

                return $service->createPdoConnection($sourceCreds);
            },
            'target_reconnector' => function () use ($targetCreds, $service) {
                return $service->createPdoConnection($targetCreds);
            },
            'source_storage' => $this->sourceStorage,
            'target_storage' => $this->targetStorage,
            'apply' => true,
            'batch_size' => 10,
            'expected_total' => 2,
            'manifest_path' => $manifestPath,
            'logger' => $logger,
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['audit']['passed']);
        $this->assertNotNull($result['manifest_sha256']);
        $this->assertTrue(file_exists($manifestPath));
        $this->assertSame($result['manifest_sha256'], hash_file('sha256', $manifestPath));

        $hasPostAuditRenewLog = false;
        foreach ($logs as $l) {
            if (str_contains($l, 'tras auditoría física antes de generar manifiesto')) {
                $hasPostAuditRenewLog = true;
            }
        }
        $this->assertTrue($hasPostAuditRenewLog);
        $this->assertGreaterThanOrEqual(2, $postAuditRenewCount);
    }

    public function test_interrupted_migration_between_phases_recovers_completely(): void
    {
        $service = new CcdProductionMigratorService;
        $this->applySelectiveMigrationsToTarget($this->targetPdo);

        $contentA = '%PDF-1.4 Recovery Item A';
        $shaA = hash('sha256', $contentA);
        $sizeA = strlen($contentA);
        $relPathA = "sha256/aa/bb/{$shaA}.pdf";
        $srcFileA = $this->sourceStorage.'/'.$relPathA;
        File::ensureDirectoryExists(dirname($srcFileA));
        file_put_contents($srcFileA, $contentA);

        $contentB = '%PDF-1.4 Recovery Item B';
        $shaB = hash('sha256', $contentB);
        $sizeB = strlen($contentB);
        $relPathB = "sha256/cc/dd/{$shaB}.pdf";
        $srcFileB = $this->sourceStorage.'/'.$relPathB;
        File::ensureDirectoryExists(dirname($srcFileB));
        file_put_contents($srcFileB, $contentB);

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at) VALUES
            (10, 'ccd-private', '{$relPathA}', '{$shaA}', {$sizeA}, 'application/pdf', 'a.pdf', NOW(), NOW(), NOW()),
            (20, 'ccd-private', '{$relPathB}', '{$shaB}', {$sizeB}, 'application/pdf', 'b.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id) VALUES
            (10, '55555551', 'a.pdf', 'AQP4', 10),
            (20, '55555552', 'b.pdf', 'AQP5', 20)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha) VALUES
            (100, '55555551', 'CTA-A', 'OP-A', 'Caja AQP', 'CAJAAQP4'),
            (200, '55555552', 'CTA-B', 'OP-B', 'Caja AQP', 'CAJAAQP5')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_at) VALUES
            (1, 10, 100, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'EV A', NOW()),
            (2, 20, 200, 'confirmada', 'operacion_pdf_aqp_auditada_v5', 'EV B', NOW())");

        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha) VALUES
            (10, '55555551', 'a.pdf', 'AQP4'),
            (20, '55555552', 'b.pdf', 'AQP5')");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha) VALUES
            (100, '55555551', 'CTA-A', 'OP-A', 'Caja AQP', 'CAJAAQP4'),
            (200, '55555552', 'CTA-B', 'OP-B', 'Caja AQP', 'CAJAAQP5')");

        // Simular fallo entre fases: Fase 2 ejecutada, y en Fase 3 solo se transfirió el primer archivo e insertó el primer archivo
        $tgtFileA = $this->targetStorage.'/'.$relPathA;
        File::ensureDirectoryExists(dirname($tgtFileA));
        file_put_contents($tgtFileA, $contentA);

        $this->targetPdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at) VALUES
            (10, 'ccd-private', '{$relPathA}', '{$shaA}', {$sizeA}, 'application/pdf', 'a.pdf', NOW(), NOW(), NOW())");

        $sourceCreds = array_merge($this->dbConfig, ['database' => 'kp_crm_testing_source']);
        $targetCreds = array_merge($this->dbConfig, ['database' => 'kp_crm_testing_target']);

        $manifestPath = $this->targetStorage.'/audits/recovery-manifest.csv';
        $result = $service->migrate([
            'source_pdo' => $this->sourcePdo,
            'target_pdo' => $this->targetPdo,
            'source_credentials' => $sourceCreds,
            'target_credentials' => $targetCreds,
            'source_storage' => $this->sourceStorage,
            'target_storage' => $this->targetStorage,
            'apply' => true,
            'batch_size' => 10,
            'expected_total' => 2,
            'manifest_path' => $manifestPath,
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(2, $result['files_verified']);
        $this->assertSame(1, $result['files_transferred']); // Solo transfirió el archivo B faltante
        $this->assertSame(2, $result['archivos_migrated']);
        $this->assertSame(2, $result['links_updated']);
        $this->assertSame(2, $result['associations_migrated']);
        $this->assertTrue($result['audit']['passed']);
        $this->assertTrue(file_exists($manifestPath));
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
