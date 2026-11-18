<?php

namespace Tests\Feature;

use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use App\Services\Ccd\CcdAqpAssociationApplyGuard;
use App\Services\Ccd\CcdAqpControlledAssociationService;
use App\Services\Ccd\CcdAqpHumanReviewReader;
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
        $fixture = $this->createPilotFixture('aqp-v3-complete-test.csv', 'aqp-human-complete-test.csv');

        $this->artisan('ccd:associate-aqp-audited', $this->commandOptions(
            'aqp-v4-dry-run-test.csv',
            8,
            1,
            4,
            4,
            'aqp-v3-complete-test.csv',
            'aqp-human-complete-test.csv',
            $fixture['human_sha256'],
        ))
            ->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/aqp-v4-dry-run-test.csv');
        $this->assertSame(8, substr_count($csv, ',elegible,'));
        $this->assertSame(8, substr_count($csv, ',dni_codigo_cliente_aqp_verificado,'));
        $this->assertSame(8, substr_count($csv, $fixture['human_sha256']));
        $this->assertStringContainsString(',dry-run', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereIn('ccd_cliente_id', collect($fixture['documents'])->pluck('id'))->count());
    }

    public function test_controlled_service_can_confirm_once_and_is_idempotent_in_testing_transaction(): void
    {
        $ccd = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $this->writeAuditCsv('aqp-v3-apply-service-test.csv', [$ccd]);
        $candidate = $this->withHumanApproval(
            app(CcdAqpV3AuditReader::class)->read(['aqp-v3-apply-service-test.csv'])['candidates'][$ccd->id],
        );
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
        $this->assertStringContainsString(
            'revision_humana_sha256:'.CcdAqpHumanReviewReader::APPROVED_SHA256,
            (string) DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->value('evidencia'),
        );
        $this->assertSame(1, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_application_service_rejects_a_non_approved_human_review_fingerprint(): void
    {
        $ccd = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');

        $result = app(CcdVerifiedAssociationService::class)->applyAqpAudited(
            $ccd,
            str_repeat('a', 64),
            str_repeat('b', 64),
        );

        $this->assertSame('revision', $result['status']);
        $this->assertSame('huella_revision_humana_no_aprobada', $result['reason']);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_ccd_id_processes_only_an_approved_human_review_record(): void
    {
        $fixture = $this->createPilotFixture('aqp-v3-single-test.csv', 'aqp-human-single-test.csv');
        $approved = $fixture['documents'][3];
        $options = $this->commandOptions(
            'aqp-v4-single-test.csv',
            8,
            1,
            4,
            4,
            'aqp-v3-single-test.csv',
            'aqp-human-single-test.csv',
            $fixture['human_sha256'],
        );
        $options['--ccd-id'] = $approved->id;

        $this->artisan('ccd:associate-aqp-audited', $options)->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/aqp-v4-single-test.csv');
        $this->assertSame(1, substr_count($csv, ',elegible,'));
        $this->assertStringContainsString("\n{$approved->id},", $csv);
        $this->assertSame(1, substr_count($csv, $fixture['human_sha256']));

        $outside = $this->createEligibleCcd('00999999', '4499999999', 'AQP4');
        $options['--ccd-id'] = $outside->id;
        $options['--output'] = 'aqp-v4-outside-test.csv';

        $this->artisan('ccd:associate-aqp-audited', $options)->assertExitCode(1);
        $this->assertFalse(Storage::disk((string) config('ccd.private_disk'))->exists('audits/aqp-v4-outside-test.csv'));
    }

    public function test_human_review_rejects_wrong_hash_and_altered_file(): void
    {
        $fixture = $this->createPilotFixture('aqp-v3-hash-test.csv', 'aqp-human-hash-test.csv');
        $options = $this->commandOptions(
            'aqp-v4-human-hash-test.csv',
            8,
            1,
            4,
            4,
            'aqp-v3-hash-test.csv',
            'aqp-human-hash-test.csv',
            str_repeat('0', 64),
        );

        $this->artisan('ccd:associate-aqp-audited', $options)->assertExitCode(1);

        $disk = Storage::disk((string) config('ccd.private_disk'));
        $path = 'audits/aqp-human-hash-test.csv';
        $disk->put($path, $disk->get($path)."\n");
        $options['--human-review-sha256'] = $fixture['human_sha256'];

        $this->artisan('ccd:associate-aqp-audited', $options)->assertExitCode(1);
        $this->assertFalse($disk->exists('audits/aqp-v4-human-hash-test.csv'));
    }

    public function test_human_review_rejects_duplicates_unapproved_rows_and_audit_fingerprint_mismatch(): void
    {
        foreach (['duplicate', 'unapproved', 'audit-mismatch', 'observation'] as $case) {
            $auditName = "aqp-v3-human-{$case}-test.csv";
            $humanName = "aqp-human-{$case}-test.csv";
            $documents = $this->createPilotDocuments();
            $this->writeAuditCsv($auditName, $documents);
            $humanSha = $this->writeHumanReviewCsv(
                $humanName,
                $documents,
                $auditName,
                static function (array $rows) use ($case): array {
                    if ($case === 'duplicate') {
                        $rows[1]['ccd_id'] = $rows[0]['ccd_id'];
                    } elseif ($case === 'unapproved') {
                        $rows[0]['resultado_manual'] = 'RECHAZADO';
                    } elseif ($case === 'audit-mismatch') {
                        $rows[0]['auditoria_v3_sha256'] = str_repeat('a', 64);
                    } else {
                        $rows[0]['observacion'] = 'La operación no coincide';
                    }

                    return $rows;
                },
            );

            $this->artisan('ccd:associate-aqp-audited', $this->commandOptions(
                "aqp-v4-human-{$case}-test.csv",
                8,
                1,
                4,
                4,
                $auditName,
                $humanName,
                $humanSha,
            ))->assertExitCode(1);

            $this->assertFalse(Storage::disk((string) config('ccd.private_disk'))->exists("audits/aqp-v4-human-{$case}-test.csv"));
        }
    }

    public function test_apply_command_requires_both_environment_guards_and_both_tokens(): void
    {
        $fixture = $this->createPilotFixture('aqp-v3-guard-test.csv', 'aqp-human-guard-test.csv');
        $base = $this->commandOptions(
            'aqp-v4-guard-test.csv',
            8,
            1,
            4,
            4,
            'aqp-v3-guard-test.csv',
            'aqp-human-guard-test.csv',
            $fixture['human_sha256'],
        );

        $this->artisan('ccd:associate-aqp-audited', array_merge($base, ['--apply' => true]))
            ->assertExitCode(1);

        $this->artisan('ccd:associate-aqp-audited', array_merge($base, [
            '--apply' => true,
            '--authorization' => 'CONFIRMAR_ASOCIACION_AQP_AUDITADA',
            '--confirmation' => 'REVALIDAR_PDF_Y_CONFIRMAR_AQP',
        ]))->assertExitCode(1);

        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereIn('ccd_cliente_id', collect($fixture['documents'])->pluck('id'))->count());
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

        $wrongResult = $service->analyze($wrongCode, $this->withHumanApproval($audit['candidates'][$wrongCode->id]));
        $wrongDniResult = $service->analyze($wrongDni, $this->withHumanApproval($audit['candidates'][$wrongDni->id]));
        $ambiguousResult = $service->analyze($ambiguous, $this->withHumanApproval($audit['candidates'][$ambiguous->id]));
        $conflictResult = $service->analyze($conflicted, $this->withHumanApproval($audit['candidates'][$conflicted->id]));

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

        $changedResult = $service->analyze($changed, $this->withHumanApproval($audit['candidates'][$changed->id]));
        $missingResult = $service->analyze($missing, $this->withHumanApproval($audit['candidates'][$missing->id]));

        $this->assertSame('error', $changedResult['status']);
        $this->assertSame('archivo_privado_sha256_no_coincide', $changedResult['reason']);
        $this->assertSame('error', $missingResult['status']);
        $this->assertSame('archivo_privado_no_disponible', $missingResult['reason']);
    }

    public function test_outer_transaction_rolls_back_when_document_changes_during_apply(): void
    {
        $ccd = $this->createEligibleCcd('00123456', '4400123456', 'AQP4');
        $this->writeAuditCsv('aqp-v3-rollback-test.csv', [$ccd]);
        $candidate = $this->withHumanApproval(
            app(CcdAqpV3AuditReader::class)->read(['aqp-v3-rollback-test.csv'])['candidates'][$ccd->id],
        );
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
        $fixture = $this->createPilotFixture('aqp-v3-lock-test.csv', 'aqp-human-lock-test.csv');
        $path = storage_path('framework/ccd-associate-bulk.lock');
        $handle = fopen($path, 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        try {
            $this->artisan('ccd:associate-aqp-audited', $this->commandOptions(
                'aqp-v4-lock-test.csv',
                8,
                1,
                4,
                4,
                'aqp-v3-lock-test.csv',
                'aqp-human-lock-test.csv',
                $fixture['human_sha256'],
            ))
                ->assertExitCode(1);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereIn('ccd_cliente_id', collect($fixture['documents'])->pluck('id'))->count());
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
    private function commandOptions(
        string $output,
        int $total,
        int $inputs,
        int $aqp4,
        int $aqp5,
        string $input = 'aqp-v3-complete-test.csv',
        string $humanReview = 'aqp-human-complete-test.csv',
        string $humanReviewSha256 = '',
    ): array
    {
        return [
            '--input' => [$input],
            '--human-review' => $humanReview,
            '--human-review-sha256' => $humanReviewSha256,
            '--expected-human-total' => 8,
            '--expected-human-aqp4' => 4,
            '--expected-human-aqp5' => 4,
            '--expected-human-per-stratum' => 1,
            '--limit' => $total,
            '--expected-total' => $total,
            '--expected-inputs' => $inputs,
            '--expected-aqp4' => $aqp4,
            '--expected-aqp5' => $aqp5,
            '--output' => $output,
        ];
    }

    /** @return array{documents:array<int,CcdCliente>,human_sha256:string} */
    private function createPilotFixture(string $auditName, string $humanName): array
    {
        $documents = $this->createPilotDocuments();
        $this->writeAuditCsv($auditName, $documents);

        return [
            'documents' => $documents,
            'human_sha256' => $this->writeHumanReviewCsv($humanName, $documents, $auditName),
        ];
    }

    /** @return array<int,CcdCliente> */
    private function createPilotDocuments(): array
    {
        $documents = [];

        foreach (['AQP4' => '44', 'AQP5' => '55'] as $harvest => $prefix) {
            foreach (range(1, 4) as $index) {
                $dni = str_pad((string) (($harvest === 'AQP4' ? 1_000 : 2_000) + $index), 8, '0', STR_PAD_LEFT);
                $documents[] = $this->createEligibleCcd($dni, $prefix.str_pad((string) $index, 8, '0', STR_PAD_LEFT), $harvest);
            }
        }

        return $documents;
    }

    /**
     * @param  array<int,CcdCliente>  $documents
     * @param  (callable(array<int,array<string,string>>):array<int,array<string,string>>)|null  $mutate
     */
    private function writeHumanReviewCsv(string $name, array $documents, string $auditName, ?callable $mutate = null): string
    {
        $disk = Storage::disk((string) config('ccd.private_disk'));
        $auditSha256 = hash_file('sha256', $disk->path('audits/'.$auditName));
        $this->assertIsString($auditSha256);
        $indices = ['AQP4' => 0, 'AQP5' => 0];
        $rows = [];

        foreach ($documents as $ccd) {
            $indices[$ccd->cosecha]++;
            $rows[] = [
                'ccd_id' => (string) $ccd->id,
                'cosecha' => $ccd->cosecha,
                'tramo_historico' => 'Q'.$indices[$ccd->cosecha],
                'auditoria_v3_sha256' => $auditSha256,
                'identidad_revisada' => 'SI',
                'operacion_revisada' => 'SI',
                'resultado_manual' => 'APROBADO',
                'observacion' => '',
            ];
        }

        if ($mutate !== null) {
            $rows = $mutate($rows);
        }

        $header = ['ccd_id', 'cosecha', 'tramo_historico', 'auditoria_v3_sha256', 'identidad_revisada', 'operacion_revisada', 'resultado_manual', 'observacion'];
        $stream = fopen($disk->path('audits/'.$name), 'wb');
        $this->assertIsResource($stream);
        fputcsv($stream, $header);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $column): string => $row[$column], $header));
        }

        fclose($stream);
        $sha256 = hash_file('sha256', $disk->path('audits/'.$name));
        $this->assertIsString($sha256);

        return $sha256;
    }

    /** @param array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string} $candidate */
    private function withHumanApproval(array $candidate): array
    {
        return $candidate + [
            'human_review_sha256' => CcdAqpHumanReviewReader::APPROVED_SHA256,
            'human_stratum' => 'Q1',
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
