<?php

namespace Tests\Feature;

use App\Models\CcdArchivo;
use App\Models\CcdAssociationRun;
use App\Services\Ccd\CcdVerifiedAssociationService;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class CcdBulkAssociationTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int,string> */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('ccd.private_disk'));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    /** @dataProvider simulatedBatchSizes */
    public function test_bulk_simulation_processes_bounded_sets_without_offset_or_writes(int $documents, int $batchSize): void
    {
        [$firstId, $lastId] = $this->createCcdRows($documents, 'BULK_SIM_'.$documents);
        $service = Mockery::mock(CcdVerifiedAssociationService::class);
        $service->shouldReceive('analyze')->times($documents)->andReturnUsing(fn ($ccd): array => $this->eligibleResult((int) $ccd->id));
        $service->shouldNotReceive('apply');
        app()->instance(CcdVerifiedAssociationService::class, $service);

        $this->artisan('ccd:associate-bulk', [
            '--batch-size' => $batchSize,
            '--max-documents' => $documents,
            '--from-id' => $firstId,
            '--to-id' => $lastId,
            '--output' => "bulk-{$documents}-test.csv",
        ])->assertExitCode(0);

        $run = CcdAssociationRun::query()->latest('id')->firstOrFail();
        $this->assertSame($documents, $run->processed);
        $this->assertSame($documents, $run->eligible);
        $this->assertSame(CcdAssociationRun::STATUS_COMPLETED, $run->status);
        $this->assertSame($lastId, $run->last_ccd_id);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereBetween('ccd_cliente_id', [$firstId, $lastId])->count());
        $this->assertTrue(Storage::disk((string) config('ccd.private_disk'))->exists("audits/bulk-{$documents}-test.csv"));

        if ($documents === 1000) {
            $this->assertLessThan(100, $run->last_query_count, 'El lote sintético no debe degradar a consultas N+1.');
        }
    }

    public static function simulatedBatchSizes(): array
    {
        return [
            '10 documents' => [10, 10],
            '100 documents' => [100, 100],
            '1000 documents' => [1000, 100],
        ];
    }

    public function test_bulk_run_resumes_from_persisted_primary_key_checkpoint(): void
    {
        [$firstId, $lastId] = $this->createCcdRows(25, 'BULK_RESUME');
        $service = Mockery::mock(CcdVerifiedAssociationService::class);
        $service->shouldReceive('analyze')->times(25)->andReturnUsing(fn ($ccd): array => $this->eligibleResult((int) $ccd->id));
        app()->instance(CcdVerifiedAssociationService::class, $service);

        $this->artisan('ccd:associate-bulk', [
            '--batch-size' => 5,
            '--max-documents' => 10,
            '--from-id' => $firstId,
            '--to-id' => $lastId,
            '--output' => 'bulk-resume-1-test.csv',
        ])->assertExitCode(0);

        $run = CcdAssociationRun::query()->latest('id')->firstOrFail();
        $this->assertSame(CcdAssociationRun::STATUS_PAUSED, $run->status);
        $this->assertSame(10, $run->processed);

        $this->artisan('ccd:associate-bulk', [
            '--batch-size' => 10,
            '--max-documents' => 10,
            '--resume' => $run->execution_id,
            '--output' => 'bulk-resume-2-test.csv',
        ])->assertExitCode(0);

        $run->refresh();
        $this->assertSame(20, $run->processed);
        $this->assertSame(CcdAssociationRun::STATUS_PAUSED, $run->status);

        $this->artisan('ccd:associate-bulk', [
            '--batch-size' => 10,
            '--max-documents' => 10,
            '--resume' => $run->execution_id,
            '--output' => 'bulk-resume-3-test.csv',
        ])->assertExitCode(0);

        $run->refresh();
        $this->assertSame(25, $run->processed);
        $this->assertSame(25, $run->eligible);
        $this->assertSame($lastId, $run->last_ccd_id);
        $this->assertSame(CcdAssociationRun::STATUS_COMPLETED, $run->status);
    }

    public function test_bulk_skips_preconfirmed_pdf_unless_explicitly_audited(): void
    {
        [$ccdId] = $this->createCcdRows(1, 'BULK_CONFIRMED');
        $accountId = (int) DB::table('clientes_cuentas')->insertGetId([
            'numdoc' => '00000001',
            'operacion' => '000123456',
            'cuenta' => 'BULK-ACCOUNT',
            'cosecha' => 'BULK_CONFIRMED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ccd_cliente_cuenta')->insert([
            'ccd_cliente_id' => $ccdId,
            'cliente_cuenta_id' => $accountId,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'revision_visual_pdf',
            'evidencia' => 'Confirmación previa de prueba.',
            'confirmado_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = Mockery::mock(CcdVerifiedAssociationService::class);
        $service->shouldNotReceive('analyze');
        $service->shouldNotReceive('apply');
        app()->instance(CcdVerifiedAssociationService::class, $service);

        $this->artisan('ccd:associate-bulk', [
            '--from-id' => $ccdId,
            '--to-id' => $ccdId,
            '--max-documents' => 1,
            '--output' => 'bulk-preconfirmed-test.csv',
        ])->assertExitCode(0);

        $run = CcdAssociationRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $run->already_confirmed);
        $this->assertSame('revision_visual_pdf', DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccdId)->value('metodo_verificacion'));

        $auditService = Mockery::mock(CcdVerifiedAssociationService::class);
        $auditService->shouldReceive('analyze')->once()->andReturn([
            'status' => 'conflicto',
            'source' => 'privado',
            'account_id' => $accountId,
            'operacion' => '000123456',
            'evidence' => 'dni_documental_no_coincide',
            'dni_evidence' => 'dni_documental_contradictorio',
            'method' => 'operacion_pdf_automatica',
            'reason' => 'dni_documental_contradice_confirmacion_existente',
        ]);
        app()->instance(CcdVerifiedAssociationService::class, $auditService);

        $this->artisan('ccd:associate-bulk', [
            '--from-id' => $ccdId,
            '--to-id' => $ccdId,
            '--max-documents' => 1,
            '--audit-confirmed' => true,
            '--output' => 'bulk-audit-confirmed-test.csv',
        ])->assertExitCode(0);

        $auditRun = CcdAssociationRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $auditRun->conflicts);
        $this->assertSame('revision_visual_pdf', DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccdId)->value('metodo_verificacion'));
    }

    public function test_bulk_stops_at_error_threshold_and_can_resume(): void
    {
        [$firstId, $lastId] = $this->createCcdRows(5, 'BULK_ERRORS');
        $service = Mockery::mock(CcdVerifiedAssociationService::class);
        $service->shouldReceive('analyze')->times(2)->andReturn($this->errorResult());
        app()->instance(CcdVerifiedAssociationService::class, $service);

        $this->artisan('ccd:associate-bulk', [
            '--batch-size' => 5,
            '--max-documents' => 5,
            '--max-errors' => 2,
            '--from-id' => $firstId,
            '--to-id' => $lastId,
            '--output' => 'bulk-errors-test.csv',
        ])->assertExitCode(1);

        $run = CcdAssociationRun::query()->latest('id')->firstOrFail();
        $this->assertSame(CcdAssociationRun::STATUS_FAILED, $run->status);
        $this->assertSame('limite_errores', $run->stop_reason);
        $this->assertSame(2, $run->processed);
        $this->assertSame(2, $run->errors);
        $this->assertLessThan($lastId, $run->last_ccd_id);
    }

    public function test_bulk_rejects_a_second_concurrent_process(): void
    {
        $path = storage_path('framework/ccd-associate-bulk.lock');
        $handle = fopen($path, 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        try {
            $this->artisan('ccd:associate-bulk', ['--max-documents' => 1])
                ->expectsOutputToContain('otro proceso')
                ->assertExitCode(1);
            $this->assertSame(0, CcdAssociationRun::query()->count());
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function test_bulk_apply_remains_blocked_outside_exact_staging_database(): void
    {
        [$firstId] = $this->createCcdRows(1, 'BULK_APPLY_GUARD');

        $this->artisan('ccd:associate-bulk', [
            '--from-id' => $firstId,
            '--to-id' => $firstId,
            '--max-documents' => 1,
            '--apply' => true,
            '--authorization' => 'CONFIRMAR_ASOCIACION_AUTOMATICA',
        ])->assertExitCode(1);

        $this->assertSame(0, CcdAssociationRun::query()->count());
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $firstId)->count());
    }

    public function test_bulk_simulation_integrates_private_pdf_and_zip_v12_without_writing(): void
    {
        $privateDni = '80201928';
        $privateOperation = '2744794';
        $this->createAccount($privateDni, $privateOperation, 'AQP1', 'CTA-BULK-PRIVATE', 'Caja AQP');
        $privatePdf = $this->pdf("DNI: {$privateDni}\nOperación: {$privateOperation}");
        $sha256 = hash('sha256', $privatePdf);
        $privatePath = CcdArchivo::pathForHash($sha256);
        Storage::disk((string) config('ccd.private_disk'))->put($privatePath, $privatePdf);
        $archivoId = (int) DB::table('ccd_archivos')->insertGetId([
            'disk' => (string) config('ccd.private_disk'),
            'path' => $privatePath,
            'sha256' => $sha256,
            'size_bytes' => strlen($privatePdf),
            'mime_type' => 'application/pdf',
            'nombre_original' => 'CCD_AQP1_BULK.pdf',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $privateCcdId = (int) DB::table('ccd_clientes')->insertGetId([
            'numdoc' => $privateDni,
            'pdf' => 'CCD_AQP1_BULK.pdf',
            'cosecha' => 'AQP1',
            'link' => null,
            'ccd_archivo_id' => $archivoId,
        ]);

        $zipDni = '00003862';
        $zipOperation = '6878399';
        $zipPdfName = 'CCD_CONFIANZA1_BULK.pdf';
        $this->createAccount($zipDni, $zipOperation, 'CONFIANZA', 'CTA-BULK-ZIP', 'FINANCIERA CONFIANZA');
        $zipCcdId = (int) DB::table('ccd_clientes')->insertGetId([
            'numdoc' => $zipDni,
            'pdf' => $zipPdfName,
            'cosecha' => 'CONFIANZA1',
            'link' => null,
            'ccd_archivo_id' => null,
        ]);

        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ccd-bulk-zip-'.uniqid('', true);
        $temporary = $root.DIRECTORY_SEPARATOR.'temporary';
        File::makeDirectory($root, 0700, true);
        File::makeDirectory($temporary, 0700, true);
        $this->temporaryDirectories[] = $root;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($root.DIRECTORY_SEPARATOR.'CONFIANZA1.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString($zipPdfName, $this->pdf("DNI: {$zipDni}\n{$zipOperation}DEUDAACTUAL"));
        $zip->close();
        config([
            'ccd.zip_v12_root' => $root,
            'ccd.zip_v12_temp_root' => $temporary,
            'ccd.zip_v12_cosechas' => ['CONFIANZA1'],
        ]);

        $this->artisan('ccd:associate-bulk', [
            '--batch-size' => 2,
            '--max-documents' => 2,
            '--from-id' => min($privateCcdId, $zipCcdId),
            '--to-id' => max($privateCcdId, $zipCcdId),
            '--output' => 'bulk-private-zip-test.csv',
        ])->assertExitCode(0);

        $run = CcdAssociationRun::query()->latest('id')->firstOrFail();
        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/bulk-private-zip-test.csv');
        $this->assertSame(2, $run->eligible);
        $this->assertStringContainsString('privado', $csv);
        $this->assertStringContainsString('zip_v12', $csv);
        $this->assertSame([], File::files($temporary));
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereIn('ccd_cliente_id', [$privateCcdId, $zipCcdId])->count());
    }

    /** @return array{0:int,1:int} */
    private function createCcdRows(int $count, string $harvest): array
    {
        $firstId = 0;
        $lastId = 0;

        foreach (array_chunk(range(1, $count), 250) as $chunk) {
            $rows = [];

            foreach ($chunk as $number) {
                $rows[] = [
                    'numdoc' => str_pad((string) $number, 8, '0', STR_PAD_LEFT),
                    'pdf' => "CCD_{$harvest}_{$number}.pdf",
                    'cosecha' => $harvest,
                    'link' => null,
                    'ccd_archivo_id' => null,
                ];
            }

            DB::table('ccd_clientes')->insert($rows);
            $maximum = (int) DB::table('ccd_clientes')->where('cosecha', $harvest)->max('id');
            $minimum = (int) DB::table('ccd_clientes')->where('cosecha', $harvest)->min('id');
            $firstId = $firstId === 0 ? $minimum : min($firstId, $minimum);
            $lastId = max($lastId, $maximum);
        }

        return [$firstId, $lastId];
    }

    private function eligibleResult(int $ccdId): array
    {
        return [
            'status' => 'elegible',
            'source' => 'privado',
            'account_id' => $ccdId,
            'operacion' => (string) $ccdId,
            'evidence' => 'operacion_documental_exacta',
            'dni_evidence' => 'dni_documental_etiquetado',
            'method' => 'operacion_pdf_automatica',
            'reason' => 'operacion_y_dni_documentales_verificados',
        ];
    }

    private function errorResult(): array
    {
        return [
            'status' => 'error',
            'source' => 'no_disponible',
            'account_id' => null,
            'operacion' => null,
            'evidence' => 'procesamiento_tecnico',
            'dni_evidence' => 'no_evaluada',
            'method' => 'operacion_pdf_automatica',
            'reason' => 'procesamiento_tecnico',
        ];
    }

    private function createAccount(string $dni, string $operation, string $harvest, string $account, string $entity): int
    {
        return (int) DB::table('clientes_cuentas')->insertGetId([
            'numdoc' => $dni,
            'cuenta' => $account,
            'nombre' => 'Cliente CCD masivo',
            'operacion' => $operation,
            'entidad' => $entity,
            'producto' => 'Producto CCD',
            'cosecha' => $harvest,
            'moneda' => 'PEN',
            'deuda_capital' => 100,
            'deuda_total' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pdf(string $text): string
    {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<html><body><pre>'.e($text).'</pre></body></html>');
        $dompdf->render();

        return $dompdf->output();
    }
}
