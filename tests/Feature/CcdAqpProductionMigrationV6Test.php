<?php

namespace Tests\Feature;

use App\Console\Commands\MigrateCcdAqpProductionV6;
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
        File::ensureDirectoryExists($this->targetStorage);

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

        $this->setupSchema($this->sourcePdo);
        $this->setupSchema($this->targetPdo);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceStorage);
        File::deleteDirectory($this->targetStorage);

        parent::tearDown();
    }

    public function test_preflight_validates_matching_signatures_and_rejects_mismatch(): void
    {
        $service = new CcdProductionMigratorService;

        // Inicialmente fallará porque las tablas están vacías (espera 275,987 y 282,595)
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Discrepancia en conteo total de ccd_clientes');

        $service->validatePreflight($this->sourcePdo, $this->targetPdo);
    }

    public function test_file_transfer_copies_and_verifies_sha256(): void
    {
        $service = new CcdProductionMigratorService;

        // Crear archivo de prueba en origen
        $content = '%PDF-1.4 Simulated PDF Content for AQP';
        $sha256 = hash('sha256', $content);
        $size = strlen($content);
        $relPath = 'sha256/'.substr($sha256, 0, 2).'/'.substr($sha256, 2, 2)."/{$sha256}.pdf";

        $fullSourceFile = $this->sourceStorage.'/'.$relPath;
        File::ensureDirectoryExists(dirname($fullSourceFile));
        file_put_contents($fullSourceFile, $content);

        // Insertar en source ccd_archivos y ccd_clientes
        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', '{$relPath}', '{$sha256}', {$size}, 'application/pdf', 'test.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '12345678', 'test.pdf', 'AQP4', 1)");

        // Dry-run: solo verifica
        $dryResult = $service->transferAndVerifyFiles($this->sourcePdo, $this->sourceStorage, $this->targetStorage, false);
        $this->assertSame(0, $dryResult['transferred']);
        $this->assertSame(1, $dryResult['verified']);
        $this->assertFalse(file_exists($this->targetStorage.'/'.$relPath));

        // Apply: copia y verifica
        $applyResult = $service->transferAndVerifyFiles($this->sourcePdo, $this->sourceStorage, $this->targetStorage, true);
        $this->assertSame(1, $applyResult['transferred']);
        $this->assertSame(1, $applyResult['verified']);
        $this->assertTrue(file_exists($this->targetStorage.'/'.$relPath));
        $this->assertSame($sha256, hash_file('sha256', $this->targetStorage.'/'.$relPath));

        // Ejecutar de nuevo (idempotencia)
        $idempotentResult = $service->transferAndVerifyFiles($this->sourcePdo, $this->sourceStorage, $this->targetStorage, true);
        $this->assertSame(0, $idempotentResult['transferred']);
        $this->assertSame(1, $idempotentResult['verified']);
    }

    public function test_corrupted_source_file_is_detected(): void
    {
        $service = new CcdProductionMigratorService;

        $content = '%PDF-1.4 Real Content';
        $corruptContent = '%PDF-1.4 Corrupted Content';
        $sha256 = hash('sha256', $content);
        $size = strlen($content);
        $relPath = "sha256/aa/bb/{$sha256}.pdf";

        $fullSourceFile = $this->sourceStorage.'/'.$relPath;
        File::ensureDirectoryExists(dirname($fullSourceFile));
        file_put_contents($fullSourceFile, $corruptContent);

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (1, 'ccd-private', '{$relPath}', '{$sha256}', {$size}, 'application/pdf', 'test.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (1, '12345678', 'test.pdf', 'AQP4', 1)");

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Huella corrupta en archivo origen');

        $service->transferAndVerifyFiles($this->sourcePdo, $this->sourceStorage, $this->targetStorage, false);
    }

    public function test_apply_command_requires_both_authorization_tokens(): void
    {
        $this->artisan('ccd:migrate-aqp-production-v6', [
            '--apply' => true,
        ])->assertExitCode(1);

        $this->artisan('ccd:migrate-aqp-production-v6', [
            '--apply' => true,
            '--authorization' => MigrateCcdAqpProductionV6::APPLY_AUTHORIZATION,
            '--confirmation' => 'TOKEN_INCORRECTO',
        ])->assertExitCode(1);
    }

    public function test_data_migration_is_atomic_and_idempotent(): void
    {
        $service = new CcdProductionMigratorService;

        // Preparar fixture en source
        $content = '%PDF-1.4 Sample PDF';
        $sha = hash('sha256', $content);
        $size = strlen($content);
        $relPath = "sha256/11/22/{$sha}.pdf";

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (100, 'ccd-private', '{$relPath}', '{$sha}', {$size}, 'application/pdf', 'doc.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (500, '44123456', 'doc.pdf', 'AQP4', 100)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (700, '44123456', 'CTA-001', 'OP-001', 'CAJAAQP', 'CAJAAQP4')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_por_user_id, confirmado_at, created_at, updated_at)
                                VALUES (1, 500, 700, 'confirmada', '".CcdProductionMigratorService::METHOD_V3."', 'evidencia_test', NULL, NOW(), NOW(), NOW())");

        // En target: clientes y cuentas existen sin ccd_archivo_id ni asociaciones
        $this->targetPdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (500, '44123456', 'doc.pdf', 'AQP4', NULL)");
        $this->targetPdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (700, '44123456', 'CTA-001', 'OP-001', 'CAJAAQP', 'CAJAAQP4')");

        // Dry-run no escribe en target
        $service->migrateCcdArchivos($this->sourcePdo, $this->targetPdo, false, 500, null, 1);
        $this->assertSame('0', (string) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_archivos')->fetchColumn());

        $service->linkCcdArchivoId($this->sourcePdo, $this->targetPdo, false, 500, null, 1);
        $this->assertNull($this->targetPdo->query('SELECT ccd_archivo_id FROM ccd_clientes WHERE id = 500')->fetchColumn());

        $service->migrateAssociations($this->sourcePdo, $this->targetPdo, false, 500, null, 1);
        $this->assertSame('0', (string) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_cliente_cuenta')->fetchColumn());

        // Apply ejecuta y persiste
        $service->migrateCcdArchivos($this->sourcePdo, $this->targetPdo, true, 500, null, 1);
        $this->assertSame('1', (string) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_archivos')->fetchColumn());

        $service->linkCcdArchivoId($this->sourcePdo, $this->targetPdo, true, 500, null, 1);
        $this->assertSame('100', (string) $this->targetPdo->query('SELECT ccd_archivo_id FROM ccd_clientes WHERE id = 500')->fetchColumn());

        $service->migrateAssociations($this->sourcePdo, $this->targetPdo, true, 500, null, 1);
        $this->assertSame('1', (string) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_cliente_cuenta')->fetchColumn());

        // Re-ejecución idéntica (Idempotencia)
        $service->migrateCcdArchivos($this->sourcePdo, $this->targetPdo, true, 500, null, 1);
        $this->assertSame('1', (string) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_archivos')->fetchColumn());

        $service->linkCcdArchivoId($this->sourcePdo, $this->targetPdo, true, 500, null, 1);
        $this->assertSame('100', (string) $this->targetPdo->query('SELECT ccd_archivo_id FROM ccd_clientes WHERE id = 500')->fetchColumn());

        $service->migrateAssociations($this->sourcePdo, $this->targetPdo, true, 500, null, 1);
        $this->assertSame('1', (string) $this->targetPdo->query('SELECT COUNT(*) FROM ccd_cliente_cuenta')->fetchColumn());
    }

    public function test_manifest_generation_produces_valid_sha256(): void
    {
        $service = new CcdProductionMigratorService;

        $content = '%PDF-1.4 Manifest PDF';
        $sha = hash('sha256', $content);
        $size = strlen($content);
        $relPath = "sha256/33/44/{$sha}.pdf";

        $this->sourcePdo->exec("INSERT INTO ccd_archivos (id, disk, path, sha256, size_bytes, mime_type, nombre_original, verified_at, created_at, updated_at)
                                VALUES (10, 'ccd-private', '{$relPath}', '{$sha}', {$size}, 'application/pdf', 'doc.pdf', NOW(), NOW(), NOW())");
        $this->sourcePdo->exec("INSERT INTO ccd_clientes (id, numdoc, pdf, cosecha, ccd_archivo_id)
                                VALUES (20, '44888888', 'doc.pdf', 'AQP4', 10)");
        $this->sourcePdo->exec("INSERT INTO clientes_cuentas (id, numdoc, cuenta, operacion, entidad, cosecha)
                                VALUES (30, '44888888', 'CTA-888', 'OP-888', 'CAJAAQP', 'CAJAAQP4')");
        $this->sourcePdo->exec("INSERT INTO ccd_cliente_cuenta (id, ccd_cliente_id, cliente_cuenta_id, estado, metodo_verificacion, evidencia, confirmado_por_user_id, confirmado_at, created_at, updated_at)
                                VALUES (1, 20, 30, 'confirmada', '".CcdProductionMigratorService::METHOD_V3."', 'evidencia_manifest', NULL, NOW(), NOW(), NOW())");

        $manifestFile = storage_path('framework/testing/disks/test_manifest.csv');
        $hash = $service->generateManifest($this->sourcePdo, $this->targetPdo, $manifestFile, 1);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        $this->assertTrue(is_file($manifestFile));

        $csv = (string) file_get_contents($manifestFile);
        $this->assertStringContainsString('ccd_cliente_id,cliente_cuenta_id', $csv);
        $this->assertStringContainsString('44888888', $csv);
        $this->assertStringContainsString($sha, $csv);

        @unlink($manifestFile);
    }

    public function test_ccd_document_controller_serves_migrated_private_pdf_inline(): void
    {
        Storage::fake((string) config('ccd.private_disk'));

        $pdfContent = '%PDF-1.4 Test PDF Output';
        $sha = hash('sha256', $pdfContent);
        $path = CcdArchivo::pathForHash($sha);
        Storage::disk((string) config('ccd.private_disk'))->put($path, $pdfContent);

        $archivo = CcdArchivo::query()->create([
            'disk' => (string) config('ccd.private_disk'),
            'path' => $path,
            'sha256' => $sha,
            'size_bytes' => strlen($pdfContent),
            'mime_type' => 'application/pdf',
            'nombre_original' => 'test_document.pdf',
            'verified_at' => now(),
        ]);

        $ccd = CcdCliente::query()->create([
            'numdoc' => '77889900',
            'pdf' => 'test_document.pdf',
            'cosecha' => 'AQP4',
            'link' => null,
            'ccd_archivo_id' => $archivo->id,
        ]);

        $user = User::factory()->create();
        Gate::define('view-cliente', fn () => true);

        $response = $this->actingAs($user)->get("/clientes/{$ccd->numdoc}/ccd/{$ccd->id}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

        // IDOR: si el DNI de la URL no coincide con el del CCD, aborta 404
        $idorResponse = $this->actingAs($user)->get("/clientes/99999999/ccd/{$ccd->id}");
        $idorResponse->assertStatus(404);
    }

    private function setupSchema(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS ccd_cliente_cuenta');
        $pdo->exec('DROP TABLE IF EXISTS ccd_clientes');
        $pdo->exec('DROP TABLE IF EXISTS clientes_cuentas');
        $pdo->exec('DROP TABLE IF EXISTS ccd_archivos');

        $pdo->exec('CREATE TABLE ccd_archivos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            disk VARCHAR(64) NOT NULL,
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
}
