<?php

namespace Tests\Feature;

use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use App\Services\Ccd\CcdAqpAssociationApplyGuard;
use App\Services\Ccd\CcdAqpControlledAssociationService;
use App\Services\Ccd\CcdAqpV3AuditReader;
use App\Services\Ccd\CcdAutoAssociationApplyGuard;
use App\Services\Ccd\CcdVerifiedAssociationService;
use Dompdf\Dompdf;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CcdAqpControlledAssociationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('ccd.private_disk'));
    }

    public function test_controlled_command_dry_run_revalidates_v3_candidates_without_writes(): void
    {
        $aqp4 = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $aqp5 = $this->createEligibleCcd('00123457', '5500123457', 'AQP5');
        $this->writeAuditCsv('aqp-v3-complete-test.csv', [$aqp4, $aqp5]);

        $this->artisan('ccd:associate-aqp-audited', $this->commandOptions('aqp-v4-dry-run-test.csv', 2, 1, 1, 1))
            ->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/aqp-v4-dry-run-test.csv');
        $this->assertSame(2, substr_count($csv, ',elegible,'));
        $this->assertSame(2, substr_count($csv, ',dni_codigo_cliente_aqp_verificado,'));
        $this->assertStringContainsString(',dry-run', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereIn('ccd_cliente_id', [$aqp4->id, $aqp5->id])->count());
    }

    public function test_controlled_service_can_confirm_once_and_is_idempotent_in_testing_transaction(): void
    {
        $ccd = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $this->writeAuditCsv('aqp-v3-apply-service-test.csv', [$ccd]);
        $candidate = app(CcdAqpV3AuditReader::class)->read(['aqp-v3-apply-service-test.csv'])['candidates'][$ccd->id];
        $service = app(CcdAqpControlledAssociationService::class);

        $first = $service->apply($ccd, $candidate);
        $second = $service->apply($ccd->fresh(), $candidate);

        $this->assertSame('confirmada', $first['status']);
        $this->assertSame('ya_confirmada', $second['status']);
        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'operacion_pdf_aqp_auditada_v3',
        ]);
        $this->assertSame(1, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_apply_command_requires_both_environment_guards_and_both_tokens(): void
    {
        $ccd = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $this->writeAuditCsv('aqp-v3-guard-test.csv', [$ccd]);
        $base = $this->commandOptions('aqp-v4-guard-test.csv', 1, 1, 1, 0, 'aqp-v3-guard-test.csv');

        $this->artisan('ccd:associate-aqp-audited', array_merge($base, ['--apply' => true]))
            ->assertExitCode(1);

        $this->artisan('ccd:associate-aqp-audited', array_merge($base, [
            '--apply' => true,
            '--authorization' => 'CONFIRMAR_ASOCIACION_AQP_AUDITADA',
            '--confirmation' => 'REVALIDAR_PDF_Y_CONFIRMAR_AQP',
        ]))->assertExitCode(1);

        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_aqp_apply_guard_requires_general_specific_and_human_approval_flags(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getDatabaseName')->andReturn(CcdAutoAssociationApplyGuard::STAGING_DATABASE);
        $guard = app(CcdAqpAssociationApplyGuard::class);

        config([
            'ccd.auto_association_apply_enabled' => false,
            'ccd.aqp_association_apply_enabled' => false,
            'ccd.aqp_human_review_approved' => false,
        ]);
        $this->assertNotNull($guard->rejectionReason($connection));

        config(['ccd.auto_association_apply_enabled' => true]);
        $this->assertNotNull($guard->rejectionReason($connection));

        config(['ccd.aqp_association_apply_enabled' => true]);
        $this->assertNotNull($guard->rejectionReason($connection));

        config(['ccd.aqp_human_review_approved' => true]);
        $this->assertNull($guard->rejectionReason($connection));
    }

    public function test_reader_rejects_duplicates_and_non_v3_rows(): void
    {
        $ccd = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $this->writeAuditCsv('aqp-v3-duplicate-a.csv', [$ccd]);
        $this->writeAuditCsv('aqp-v3-duplicate-b.csv', [$ccd]);

        $this->artisan('ccd:associate-aqp-audited', [
            '--input' => ['aqp-v3-duplicate-a.csv', 'aqp-v3-duplicate-b.csv'],
            '--expected-total' => 1,
            '--expected-inputs' => 2,
            '--expected-aqp4' => 1,
            '--expected-aqp5' => 0,
            '--output' => 'aqp-v4-duplicate-test.csv',
        ])->assertExitCode(1);

        $this->writeAuditCsv('aqp-v3-invalid-row.csv', [$ccd], 'dni_documental_etiquetado');
        $this->artisan('ccd:associate-aqp-audited', [
            '--input' => ['aqp-v3-invalid-row.csv'],
            '--expected-total' => 1,
            '--expected-inputs' => 1,
            '--expected-aqp4' => 1,
            '--expected-aqp5' => 0,
            '--output' => 'aqp-v4-invalid-row-test.csv',
        ])->assertExitCode(1);

        $this->assertFalse(Storage::disk((string) config('ccd.private_disk'))->exists('audits/aqp-v4-duplicate-test.csv'));
        $this->assertFalse(Storage::disk((string) config('ccd.private_disk'))->exists('audits/aqp-v4-invalid-row-test.csv'));
    }

    public function test_revalidation_rejects_wrong_dni_code_ambiguous_operation_and_incompatible_confirmation(): void
    {
        $wrongCode = $this->createCcd('00123456', ['4400123456'], 'AQP4', '87654321');
        $wrongDni = $this->createCcd('00123459', ['4400123461'], 'AQP4', '00123459', '87654321');
        $ambiguous = $this->createCcd('00123457', ['4400123457', '4400123458'], 'AQP4', '00123457');
        $conflicted = $this->createEligibleCcd('00123458', '4400123459', 'AQP4');
        $otherAccount = $this->createAccount('00123458', '4400123460', 'AQP4', 'CTA-CONFLICT');
        DB::table('ccd_cliente_cuenta')->insert([
            'ccd_cliente_id' => $conflicted->id,
            'cliente_cuenta_id' => $otherAccount,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'revision_visual_pdf',
            'evidencia' => 'Confirmación manual previa.',
            'confirmado_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->writeAuditCsv('aqp-v3-safety-test.csv', [$wrongCode, $wrongDni, $ambiguous, $conflicted]);
        $audit = app(CcdAqpV3AuditReader::class)->read(['aqp-v3-safety-test.csv']);
        $service = app(CcdAqpControlledAssociationService::class);

        $wrongResult = $service->analyze($wrongCode, $audit['candidates'][$wrongCode->id]);
        $wrongDniResult = $service->analyze($wrongDni, $audit['candidates'][$wrongDni->id]);
        $ambiguousResult = $service->analyze($ambiguous, $audit['candidates'][$ambiguous->id]);
        $conflictResult = $service->analyze($conflicted, $audit['candidates'][$conflicted->id]);

        $this->assertSame('revision', $wrongResult['status']);
        $this->assertSame('dni_codigo_cliente_aqp_no_coincide', $wrongResult['dni_evidence']);
        $this->assertSame('revision', $wrongDniResult['status']);
        $this->assertSame('dni_documental_no_coincide', $wrongDniResult['dni_evidence']);
        $this->assertSame('revision', $ambiguousResult['status']);
        $this->assertStringContainsString('operaciones_candidatas', $ambiguousResult['evidence']);
        $this->assertSame('conflicto', $conflictResult['status']);
        $this->assertSame('revision_visual_pdf', DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $conflicted->id)->value('metodo_verificacion'));
    }

    public function test_document_fingerprint_mismatch_and_missing_pdf_stop_the_dry_run(): void
    {
        $changed = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $missing = $this->createEligibleCcd('00123457', '4400123457', 'AQP4');
        Storage::disk((string) config('ccd.private_disk'))->put($changed->archivo->path, $this->pdf('contenido modificado'));
        Storage::disk((string) config('ccd.private_disk'))->delete($missing->archivo->path);
        $this->writeAuditCsv('aqp-v3-file-integrity-test.csv', [$changed, $missing]);
        $audit = app(CcdAqpV3AuditReader::class)->read(['aqp-v3-file-integrity-test.csv']);
        $service = app(CcdAqpControlledAssociationService::class);

        $changedResult = $service->analyze($changed, $audit['candidates'][$changed->id]);
        $missingResult = $service->analyze($missing, $audit['candidates'][$missing->id]);

        $this->assertSame('error', $changedResult['status']);
        $this->assertSame('archivo_privado_sha256_no_coincide', $changedResult['reason']);
        $this->assertSame('error', $missingResult['status']);
        $this->assertSame('archivo_privado_no_disponible', $missingResult['reason']);
    }

    public function test_outer_transaction_rolls_back_when_document_changes_during_apply(): void
    {
        $ccd = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $this->writeAuditCsv('aqp-v3-rollback-test.csv', [$ccd]);
        $candidate = app(CcdAqpV3AuditReader::class)->read(['aqp-v3-rollback-test.csv'])['candidates'][$ccd->id];
        $accountId = (int) DB::table('clientes_cuentas')->where('numdoc', '00123456')->value('id');
        $verified = Mockery::mock(CcdVerifiedAssociationService::class);
        $verified->shouldReceive('applyAqpAudited')->once()->andReturnUsing(function () use ($ccd, $accountId): array {
            DB::table('ccd_cliente_cuenta')->insert([
                'ccd_cliente_id' => $ccd->id,
                'cliente_cuenta_id' => $accountId,
                'estado' => 'confirmada',
                'metodo_verificacion' => 'operacion_pdf_aqp_auditada_v3',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Storage::disk((string) config('ccd.private_disk'))->put($ccd->archivo->path, $this->pdf('cambio concurrente'));

            return $this->confirmedResult();
        });
        $service = new CcdAqpControlledAssociationService($verified);

        try {
            $service->apply($ccd, $candidate);
            $this->fail('Se esperaba detectar el cambio concurrente del PDF.');
        } catch (RuntimeException) {
            $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
        }
    }

    public function test_controlled_command_rejects_concurrent_ccd_process(): void
    {
        $ccd = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $this->writeAuditCsv('aqp-v3-lock-test.csv', [$ccd]);
        $path = storage_path('framework/ccd-associate-bulk.lock');
        $handle = fopen($path, 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        try {
            $this->artisan('ccd:associate-aqp-audited', $this->commandOptions('aqp-v4-lock-test.csv', 1, 1, 1, 0, 'aqp-v3-lock-test.csv'))
                ->assertExitCode(1);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_human_sample_is_stratified_and_contains_no_personal_or_financial_fields(): void
    {
        $documents = [];

        foreach (['AQP4', 'AQP5'] as $harvest) {
            foreach (range(1, 8) as $index) {
                $documents[] = $this->createEligibleCcd(
                    str_pad((string) ($harvest === 'AQP4' ? $index : 100 + $index), 8, '0', STR_PAD_LEFT),
                    ($harvest === 'AQP4' ? '44' : '55').str_pad((string) $index, 8, '0', STR_PAD_LEFT),
                    $harvest,
                );
            }
        }

        $this->writeAuditCsv('aqp-v3-sample-test.csv', $documents);
        $this->artisan('ccd:sample-aqp-audit', [
            '--input' => ['aqp-v3-sample-test.csv'],
            '--size-per-cosecha' => 4,
            '--expected-total' => 16,
            '--expected-inputs' => 1,
            '--expected-aqp4' => 8,
            '--expected-aqp5' => 8,
            '--output' => 'aqp-human-sample-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/aqp-human-sample-test.csv');
        $this->assertSame(4, substr_count($csv, ',AQP4,'));
        $this->assertSame(4, substr_count($csv, ',AQP5,'));
        $this->assertStringContainsString(',Q1,', $csv);
        $this->assertStringContainsString(',Q4,', $csv);
        $this->assertStringNotContainsString('numdoc', $csv);
        $this->assertStringNotContainsString('operacion,', strtolower($csv));
    }

    /** @return array<string,mixed> */
    private function commandOptions(string $output, int $total, int $inputs, int $aqp4, int $aqp5, string $input = 'aqp-v3-complete-test.csv'): array
    {
        return [
            '--input' => [$input],
            '--limit' => $total,
            '--expected-total' => $total,
            '--expected-inputs' => $inputs,
            '--expected-aqp4' => $aqp4,
            '--expected-aqp5' => $aqp5,
            '--output' => $output,
        ];
    }

    private function createEligibleCcd(string $dni, string $operation, string $harvest): CcdCliente
    {
        return $this->createCcd($dni, [$operation], $harvest, $dni);
    }

    /** @param array<int,string> $operations */
    private function createCcd(string $dni, array $operations, string $harvest, string $documentCode, ?string $explicitDni = null): CcdCliente
    {
        foreach ($operations as $index => $operation) {
            $this->createAccount($dni, $operation, $harvest, 'CTA-'.$harvest.'-'.$dni.'-'.$index);
        }

        $text = "CÓDIGO DEL CLIENTE: {$documentCode}\nSEÑOR (A): CLIENTE DE PRUEBA\n"
            .($explicitDni !== null ? "DNI: {$explicitDni}\n" : '')
            .implode("\n", $operations);
        $pdf = $this->pdf($text);
        $sha256 = hash('sha256', $pdf);
        $path = CcdArchivo::pathForHash($sha256);
        $disk = (string) config('ccd.private_disk');
        Storage::disk($disk)->put($path, $pdf);
        $archivo = CcdArchivo::create([
            'disk' => $disk,
            'path' => $path,
            'sha256' => $sha256,
            'size_bytes' => strlen($pdf),
            'mime_type' => 'application/pdf',
            'nombre_original' => 'CCD_'.$harvest.'-'.Str::random(8).'.pdf',
            'verified_at' => now(),
        ]);

        return CcdCliente::create([
            'numdoc' => $dni,
            'pdf' => 'CCD_'.$harvest.'-'.Str::random(8).'.pdf',
            'cosecha' => $harvest,
            'ccd_archivo_id' => $archivo->id,
        ])->load('archivo');
    }

    private function createAccount(string $dni, string $operation, string $harvest, string $account): int
    {
        return (int) DB::table('clientes_cuentas')->insertGetId([
            'numdoc' => $dni,
            'cuenta' => $account,
            'nombre' => 'Cliente AQP V4',
            'operacion' => $operation,
            'entidad' => 'Caja Arequipa',
            'producto' => 'Producto CCD',
            'cosecha' => $harvest,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<int,CcdCliente> $documents */
    private function writeAuditCsv(string $name, array $documents, string $dniEvidence = 'dni_codigo_cliente_aqp_verificado'): void
    {
        $lines = [
            'ccd_id,clasificacion_anterior,motivo_anterior,clasificacion_nueva,resultado_comparacion,motivo_nuevo,evidencia_operacion,evidencia_dni,fuente,cosecha',
        ];

        foreach ($documents as $ccd) {
            $lines[] = implode(',', [
                $ccd->id,
                'revision',
                'dni_documental_no_corrobable',
                'elegible',
                'ahora_elegible',
                'operacion_y_codigo_cliente_aqp_documentales_verificados',
                'operacion_candidata_en_texto_pdf',
                $dniEvidence,
                'privado',
                $ccd->cosecha,
            ]);
        }

        Storage::disk((string) config('ccd.private_disk'))->put('audits/'.$name, implode("\n", $lines)."\n");
    }

    private function pdf(string $text): string
    {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<html><body><pre>'.e($text).'</pre></body></html>');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @return array{status:string,source:string,account_id:int,operacion:string,evidence:string,method:string,reason:string,dni_evidence:string} */
    private function confirmedResult(): array
    {
        return [
            'status' => 'confirmada',
            'source' => 'privado',
            'account_id' => 1,
            'operacion' => '4400123456',
            'evidence' => 'operacion_candidata_en_texto_pdf',
            'method' => 'operacion_pdf_automatica',
            'reason' => 'confirmacion_aqp_auditada_aplicada',
            'dni_evidence' => 'dni_codigo_cliente_aqp_verificado',
        ];
    }
}
