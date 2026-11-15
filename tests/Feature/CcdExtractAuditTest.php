<?php

namespace Tests\Feature;

use App\Contracts\CcdZipV12PdfSourceProvider;
use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use App\Services\Ccd\CcdPdfIdentifierExtractor;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class CcdExtractAuditTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int,string> */
    private array $temporaryFiles = [];

    /** @var array<int,string> */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('ccd.private_disk'));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_extract_audit_finds_an_exact_operation_without_writing_an_association(): void
    {
        $dni = '05378290'; // Caso de referencia CCD 4/5.
        $operation = '0002744794';
        $this->createAccount($dni, $operation, 'AQP1', 'CTA-CCD-4');
        $ccd = $this->createPrivateCcd($dni, 'AQP1', "DNI: {$dni}\nNro. Operación: {$operation}\nEntidad: Caja AQP");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-exact-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-exact-test.csv');

        $this->assertStringContainsString('coincidencia_exacta', $csv);
        $this->assertStringContainsString($operation, $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_verified_association_simulation_is_read_only_and_reports_an_eligible_private_pdf(): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $accountId = $this->createAccount($dni, $operation, 'BBVA1', 'CTA-BBVA-4', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "DNI: {$dni}\n{$operation}");

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-simulation-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-simulation-test.csv');

        $this->assertStringContainsString('elegible', $csv);
        $this->assertStringContainsString((string) $accountId, $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_verified_association_apply_requires_explicit_authorization(): void
    {
        $dni = '05378290';
        $operation = '00110301925001679374';
        $this->createAccount($dni, $operation, 'BBVA1', 'CTA-BBVA-5', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "DNI: {$dni}\n{$operation}");

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--apply' => true,
            '--output' => 'ccd-verified-no-auth-test.csv',
        ])->assertExitCode(1);

        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_verified_association_apply_confirms_once_and_is_idempotent(): void
    {
        $dni = '80201928';
        $operation = '2744794';
        $accountId = $this->createAccount($dni, $operation, 'AQP1', 'CTA-23303');
        $ccd = $this->createPrivateCcd($dni, 'AQP1', "DNI: {$dni}\nOperación: {$operation}");
        $options = [
            '--ccd-id' => $ccd->id,
            '--apply' => true,
            '--authorization' => 'CONFIRMAR_ASOCIACION_AUTOMATICA',
        ];

        $this->artisan('ccd:associate-verified', $options + ['--output' => 'ccd-verified-apply-first-test.csv'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'operacion_pdf_automatica',
            'confirmado_por_user_id' => null,
        ]);

        $this->artisan('ccd:associate-verified', $options + ['--output' => 'ccd-verified-apply-second-test.csv'])
            ->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-apply-second-test.csv');
        $this->assertStringContainsString('ya_confirmada', $csv);
        $this->assertSame(1, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_verified_association_preserves_manual_confirmation_for_another_operation_as_conflict(): void
    {
        $dni = '05378290';
        $automaticAccountId = $this->createAccount($dni, '00110301995001707769', 'BBVA1', 'CTA-BBVA-A', 'BBVA');
        $manualAccountId = $this->createAccount($dni, '00110301925001679374', 'BBVA1', 'CTA-BBVA-B', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "DNI: {$dni}\n00110301995001707769");
        DB::table('ccd_cliente_cuenta')->insert([
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $manualAccountId,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'revision_visual_pdf',
            'evidencia' => 'Confirmación manual previa.',
            'confirmado_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-conflict-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-conflict-test.csv');
        $this->assertStringContainsString('conflicto', $csv);
        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $manualAccountId,
            'metodo_verificacion' => 'revision_visual_pdf',
        ]);
        $this->assertDatabaseMissing('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $automaticAccountId,
        ]);
    }

    public function test_verified_association_requires_documentary_dni_even_when_operation_is_exact(): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $this->createAccount($dni, $operation, 'BBVA1', 'CTA-BBVA-DNI', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "Resumen {$operation}");

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-no-dni-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-no-dni-test.csv');
        $this->assertStringContainsString('dni_documental_no_corrobable', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_verified_association_handles_generic_confianza_and_zip_source_without_writing_in_simulation(): void
    {
        $dni = '00003862';
        $operation = '6878399';
        $pdfName = 'CCD_CONFIANZA1-23550.pdf';
        $accountId = $this->createAccount($dni, $operation, 'CONFIANZA', '3303632', 'FINANCIERA CONFIANZA');
        $ccd = $this->createZipOnlyCcd($dni, 'CONFIANZA1', $pdfName);
        $this->configureZipV12([
            $pdfName => $this->pdf("DNI: {$dni}\n{$operation}DEUDAACTUAL"),
        ]);

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-confianza-zip-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-confianza-zip-test.csv');
        $this->assertStringContainsString('elegible', $csv);
        $this->assertStringContainsString((string) $accountId, $csv);
        $this->assertStringContainsString('operacion_documental_confianza_cosecha_generica', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_verified_association_sends_multiple_operations_or_textless_pdf_to_review_without_writing(): void
    {
        $dni = '05378290';
        $this->createAccount($dni, '00110301995001707769', 'BBVA1', 'CTA-BBVA-M1', 'BBVA');
        $this->createAccount($dni, '00110301925001679374', 'BBVA1', 'CTA-BBVA-M2', 'BBVA');
        $multiple = $this->createPrivateCcd($dni, 'BBVA_1_2', "DNI: {$dni}\n00110301995001707769\n00110301925001679374");
        $textless = $this->createPrivateCcd($dni, 'BBVA_1_2', '');

        foreach ([$multiple, $textless] as $index => $ccd) {
            $this->artisan('ccd:associate-verified', [
                '--ccd-id' => $ccd->id,
                '--output' => "ccd-verified-review-{$index}-test.csv",
            ])->assertExitCode(0);

            $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
        }

        $multipleCsv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-review-0-test.csv');
        $textlessCsv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-review-1-test.csv');
        $this->assertStringContainsString('revision', $multipleCsv);
        $this->assertStringContainsString('no_elegible', $textlessCsv);
    }

    public function test_verified_association_marks_repeated_operation_in_two_candidate_accounts_for_review(): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $this->createAccount($dni, $operation, 'BBVA1', 'CTA-DUPLICADA-1', 'BBVA');
        $this->createAccount($dni, $operation, 'BBVA1', 'CTA-DUPLICADA-2', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "DNI: {$dni}\n{$operation}");

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-duplicate-operation-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-duplicate-operation-test.csv');
        $this->assertStringContainsString('revision', $csv);
        $this->assertStringContainsString('ambigua', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_verified_association_does_not_match_an_operation_owned_only_by_another_dni(): void
    {
        $operation = '00110301995001707769';
        $this->createAccount('87654321', $operation, 'BBVA1', 'CTA-OTRO-DNI', 'BBVA');
        $ccd = $this->createPrivateCcd('05378290', 'BBVA_1_2', "DNI: 05378290\n{$operation}");

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-other-dni-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-other-dni-test.csv');
        $this->assertStringContainsString('no_elegible', $csv);
        $this->assertStringContainsString('sin_cuenta_compatible_por_dni_y_cosecha', $csv);
    }

    public function test_bbva_ccd_4_finds_only_the_first_unlabelled_candidate_operation(): void
    {
        [$first, $second] = $this->createBbvaCandidates('05378290');
        $ccd = $this->createPrivateCcd('05378290', 'BBVA_1_2', "DNI: 05378290\nResumen BBVA {$first}");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-bbva-4-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-bbva-4-test.csv');

        $this->assertStringContainsString('coincidencia_exacta', $csv);
        $this->assertStringContainsString($first, $csv);
        $this->assertStringContainsString('operacion_candidata_en_texto_pdf', $csv);
        $this->assertStringNotContainsString($second.',coincidencia_exacta', $csv);
    }

    public function test_bbva_ccd_5_finds_only_the_second_unlabelled_candidate_operation_with_separators(): void
    {
        [$first, $second] = $this->createBbvaCandidates('05378290');
        $formattedSecond = '0011030192-5001679374';
        $ccd = $this->createPrivateCcd('05378290', 'BBVA_1_2', "DNI: 05378290\nResumen BBVA {$formattedSecond}");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-bbva-5-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-bbva-5-test.csv');

        $this->assertStringContainsString('coincidencia_exacta', $csv);
        $this->assertStringContainsString($second, $csv);
        $this->assertStringNotContainsString($first.',coincidencia_exacta', $csv);
    }

    public function test_bbva_unlabelled_document_with_both_candidate_operations_is_ambiguous(): void
    {
        [$first, $second] = $this->createBbvaCandidates('05378290');
        $ccd = $this->createPrivateCcd('05378290', 'BBVA_1_2', "DNI: 05378290\n{$first}\n{$second}");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-bbva-ambiguous-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-bbva-ambiguous-test.csv');

        $this->assertStringContainsString('ambigua', $csv);
        $this->assertStringContainsString('operaciones_candidatas_en_texto_pdf', $csv);
    }

    public function test_bbva_operation_embedded_in_a_larger_number_is_not_a_match(): void
    {
        [$first] = $this->createBbvaCandidates('05378290');
        $ccd = $this->createPrivateCcd('05378290', 'BBVA_1_2', "DNI: 05378290\n9{$first}8");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-bbva-substring-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-bbva-substring-test.csv');

        $this->assertStringContainsString('sin_coincidencia', $csv);
        $this->assertStringNotContainsString('operacion_candidata_en_texto_pdf', $csv);
    }

    public function test_bbva_unlabelled_document_without_a_candidate_operation_stays_without_match(): void
    {
        $this->createBbvaCandidates('05378290');
        $ccd = $this->createPrivateCcd('05378290', 'BBVA_1_2', 'DNI: 05378290'."\nReferencia 99887766554433221100");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-bbva-absent-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-bbva-absent-test.csv');

        $this->assertStringContainsString('sin_coincidencia', $csv);
    }

    public function test_explicit_conflicting_dni_overrides_a_candidate_operation_found_in_text(): void
    {
        [$first] = $this->createBbvaCandidates('05378290');
        $ccd = $this->createPrivateCcd('05378290', 'BBVA_1_2', "DNI: 87654321\n{$first}");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-bbva-conflicting-dni-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-bbva-conflicting-dni-test.csv');

        $this->assertStringContainsString('sin_coincidencia', $csv);
        $this->assertStringContainsString('dni_documental_no_coincide', $csv);
    }

    public function test_ccd_23303_style_operation_keeps_its_existing_exact_match(): void
    {
        $dni = '80201928';
        $operation = '2744794';
        $this->createAccount($dni, $operation, 'AQP1', 'CTA-23303');
        $ccd = $this->createPrivateCcd($dni, 'AQP1', "DNI: {$dni}\nOperación: {$operation}");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-23303-regression-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-23303-regression-test.csv');

        $this->assertStringContainsString('coincidencia_exacta', $csv);
        $this->assertStringContainsString('operacion_documental_exacta', $csv);
    }

    public function test_ccd_23550_style_confianza_document_matches_generic_crm_harvest_only_by_operation(): void
    {
        $dni = '00003862';
        $operation = '6878399';
        $this->createAccount($dni, $operation, 'CONFIANZA', '3303632', 'FINANCIERA CONFIANZA');
        $ccd = $this->createPrivateCcd($dni, 'CONFIANZA1', "DNI: {$dni}\n{$operation}DEUDAACTUAL");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-23550-confianza-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-23550-confianza-test.csv');

        $this->assertStringContainsString('coincidencia_exacta', $csv);
        $this->assertStringContainsString($operation, $csv);
        $this->assertStringContainsString('operacion_documental_confianza_cosecha_generica', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_generic_confianza_harvest_with_multiple_documented_operations_is_ambiguous(): void
    {
        $dni = '00003862';
        $this->createAccount($dni, '6878399', 'CONFIANZA', '3303632', 'FINANCIERA CONFIANZA');
        $this->createAccount($dni, '6878400', 'CONFIANZA', '3303633', 'FINANCIERA CONFIANZA');
        $ccd = $this->createPrivateCcd($dni, 'CONFIANZA1', "DNI: {$dni}\n6878399DEUDA\n6878400DEUDA");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-confianza-ambiguous-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-confianza-ambiguous-test.csv');

        $this->assertStringContainsString('ambigua', $csv);
        $this->assertStringContainsString('operaciones_documentales_confianza_cosecha_generica', $csv);
    }

    public function test_generic_confianza_harvest_without_full_document_operation_stays_unmatched(): void
    {
        $dni = '00003862';
        $this->createAccount($dni, '6878399', 'CONFIANZA', '3303632', 'FINANCIERA CONFIANZA');
        $ccd = $this->createPrivateCcd($dni, 'CONFIANZA1', "DNI: {$dni}\n687839DEUDAACTUAL");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-confianza-partial-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-confianza-partial-test.csv');

        $this->assertStringContainsString('sin_coincidencia', $csv);
        $this->assertStringContainsString('confianza_cosecha_generica_sin_operacion_documental', $csv);
    }

    public function test_generic_confianza_harvest_does_not_accept_another_entity(): void
    {
        $dni = '00003862';
        $this->createAccount($dni, '6878399', 'CONFIANZA', '3303632', 'OTRA FINANCIERA');
        $ccd = $this->createPrivateCcd($dni, 'CONFIANZA1', "DNI: {$dni}\n6878399DEUDAACTUAL");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-confianza-other-entity-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-confianza-other-entity-test.csv');

        $this->assertStringContainsString('sin_cuenta_compatible_por_dni_y_cosecha', $csv);
    }

    public function test_specific_confianza_harvest_keeps_priority_over_generic_homologation(): void
    {
        $dni = '00003862';
        $operation = '6878399';
        $this->createAccount($dni, $operation, 'CONFIANZA1', '3303632', 'FINANCIERA CONFIANZA');
        $ccd = $this->createPrivateCcd($dni, 'CONFIANZA1', "DNI: {$dni}\nOperación: {$operation}");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-confianza-specific-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-confianza-specific-test.csv');

        $this->assertStringContainsString('operacion_documental_exacta', $csv);
        $this->assertStringNotContainsString('cosecha_generica', $csv);
    }

    public function test_every_configured_confianza_zip_harvest_can_use_the_generic_crm_harvest_with_documentary_operation(): void
    {
        $cosechas = [
            'CONFIANZA1', 'CONFIANZA2', 'CONFIANZA4', 'CONFIANZA5', 'CONFIANZA6', 'CONFIANZA7',
            'CONFIANZA8', 'CONFIANZA9', 'CONFIANZA10', 'CONFIANZA11', 'CONFIANZA12',
        ];

        foreach ($cosechas as $index => $cosecha) {
            $dni = str_pad((string) (3800 + $index), 8, '0', STR_PAD_LEFT);
            $operation = (string) (6_878_399 + $index);
            $this->createAccount($dni, $operation, 'CONFIANZA', 'CTA-CONFIANZA-'.$index, 'FINANCIERA CONFIANZA');
            $ccd = $this->createPrivateCcd($dni, $cosecha, "DNI: {$dni}\n{$operation}DEUDAACTUAL");

            $this->artisan('ccd:extract-audit', [
                '--ccd-id' => $ccd->id,
                '--output' => "ccd-{$cosecha}-generic-test.csv",
            ])->assertExitCode(0);

            $csv = Storage::disk((string) config('ccd.private_disk'))->get("audits/ccd-{$cosecha}-generic-test.csv");
            $this->assertStringContainsString('operacion_documental_confianza_cosecha_generica', $csv);
        }
    }

    public function test_extract_audit_does_not_resolve_a_shared_account_without_an_operation(): void
    {
        $dni = '80201928'; // Caso de referencia CCD 23303.
        $account = 'CTA-COMPARTIDA';
        $this->createAccount($dni, '2744794', 'AQP1', $account);
        $this->createAccount($dni, '2744795', 'AQP1', $account);
        $ccd = $this->createPrivateCcd($dni, 'AQP1', "DNI: {$dni}\nNúmero de cuenta: {$account}\nEntidad: Caja AQP");

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-account-only-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-account-only-test.csv');

        $this->assertStringContainsString('coincidencia_cuenta', $csv);
        $this->assertStringContainsString('cuenta_documental_con_varias_operaciones', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_extract_audit_marks_a_textless_pdf_for_review(): void
    {
        $ccd = $this->createPrivateCcd('05378290', 'AQP1', '');

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-no-text-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-no-text-test.csv');

        $this->assertStringContainsString('sin_texto', $csv);
    }

    public function test_extract_audit_can_read_from_a_read_only_zip_v12_adapter(): void
    {
        $dni = '05378290';
        $operation = '0001382';
        $this->createAccount($dni, $operation, 'AQP1', 'CTA-ZIP-ONLY');
        $pdfPath = $this->writeTemporaryPdf("DNI: {$dni}\nOperación: {$operation}\nEntidad financiera: Caja AQP");
        $ccd = CcdCliente::create([
            'numdoc' => $dni,
            'pdf' => 'CCD_AQP1-1382.pdf',
            'cosecha' => 'AQP1',
            'link' => 'https://storage.kpinvest.pe/ccd/legacy.zip',
        ]);

        config(['ccd.zip_v12_source_provider' => new class($pdfPath) implements CcdZipV12PdfSourceProvider {
            public function __construct(private readonly string $path) {}

            public function sourcePath(CcdCliente $ccd): ?string
            {
                return $this->path;
            }
        }]);

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-zip-v12-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-zip-v12-test.csv');

        $this->assertStringContainsString('zip_v12', $csv);
        $this->assertStringContainsString('coincidencia_exacta', $csv);
    }

    public function test_extract_audit_reads_a_zip_v12_entry_and_removes_its_private_temporary_file(): void
    {
        $dni = '05378290';
        $operation = '0001382';
        $pdfName = 'CCD_CONFIANZA1-1382.pdf';
        $this->createAccount($dni, $operation, 'CONFIANZA1', 'CTA-ZIP-V12');
        $ccd = $this->createZipOnlyCcd($dni, 'CONFIANZA1', $pdfName);
        $environment = $this->configureZipV12([
            $pdfName => $this->pdf("DNI: {$dni}\nOperación: {$operation}\nEntidad: Caja AQP"),
        ]);

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-real-zip-v12-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-real-zip-v12-test.csv');

        $this->assertStringContainsString('zip_v12', $csv);
        $this->assertStringContainsString('coincidencia_exacta', $csv);
        $this->assertSame([], File::files($environment['temporary']));
    }

    public function test_extract_audit_reports_missing_zip_entry_without_creating_a_temporary_file(): void
    {
        $ccd = $this->createZipOnlyCcd('05378290', 'CONFIANZA1', 'CCD_CONFIANZA1-AUSENTE.pdf');
        $environment = $this->configureZipV12([
            'CCD_CONFIANZA1-OTRO.pdf' => $this->pdf('DNI: 05378290'),
        ]);

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-zip-missing-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-zip-missing-test.csv');

        $this->assertStringContainsString('pdf_zip_v12_no_encontrado', $csv);
        $this->assertSame([], File::files($environment['temporary']));
    }

    public function test_extract_audit_rejects_an_unsafe_zip_entry_name(): void
    {
        $ccd = $this->createZipOnlyCcd('05378290', 'CONFIANZA1', '../fuera.pdf');
        $this->configureZipV12([]);

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-zip-invalid-name-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-zip-invalid-name-test.csv');

        $this->assertStringContainsString('nombre_pdf_zip_v12_invalido', $csv);
    }

    public function test_extract_audit_rejects_oversized_and_invalid_signature_zip_entries(): void
    {
        $oversized = $this->createZipOnlyCcd('05378290', 'CONFIANZA1', 'CCD_CONFIANZA1-GRANDE.pdf');
        $this->configureZipV12([
            'CCD_CONFIANZA1-GRANDE.pdf' => '%PDF-'.str_repeat('x', 100),
            'CCD_CONFIANZA1-FIRMA.pdf' => 'NO ES UN PDF',
        ], 32);

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $oversized->id,
            '--output' => 'ccd-zip-oversized-test.csv',
        ])->assertExitCode(0);

        $signature = $this->createZipOnlyCcd('05378290', 'CONFIANZA1', 'CCD_CONFIANZA1-FIRMA.pdf');
        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $signature->id,
            '--output' => 'ccd-zip-signature-test.csv',
        ])->assertExitCode(0);

        $disk = Storage::disk((string) config('ccd.private_disk'));
        $this->assertStringContainsString('pdf_zip_v12_tamano_invalido', $disk->get('audits/ccd-zip-oversized-test.csv'));
        $this->assertStringContainsString('pdf_zip_v12_firma_invalida', $disk->get('audits/ccd-zip-signature-test.csv'));
    }

    public function test_extract_audit_reports_a_damaged_or_unavailable_zip(): void
    {
        $ccd = $this->createZipOnlyCcd('05378290', 'CONFIANZA1', 'CCD_CONFIANZA1-DAÑADO.pdf');
        $environment = $this->configureZipV12([], null, false);
        file_put_contents($environment['root'].DIRECTORY_SEPARATOR.'CONFIANZA1.zip', 'no es un zip');

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-zip-damaged-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-zip-damaged-test.csv');

        $this->assertStringContainsString('zip_v12_danado', $csv);

        $unavailable = $this->createZipOnlyCcd('05378290', 'CONFIANZA1', 'CCD_CONFIANZA1-AUSENTE.zip.pdf');
        $this->configureZipV12([], null, false);
        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $unavailable->id,
            '--output' => 'ccd-zip-unavailable-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-zip-unavailable-test.csv');
        $this->assertStringContainsString('zip_v12_no_disponible', $csv);
    }

    public function test_extract_audit_removes_zip_temporary_file_when_extraction_throws(): void
    {
        $pdfName = 'CCD_CONFIANZA1-ERROR.pdf';
        $ccd = $this->createZipOnlyCcd('05378290', 'CONFIANZA1', $pdfName);
        $environment = $this->configureZipV12([
            $pdfName => $this->pdf('DNI: 05378290'),
        ]);
        app()->bind(CcdPdfIdentifierExtractor::class, static fn (): CcdPdfIdentifierExtractor => new class extends CcdPdfIdentifierExtractor {
            public function extract(string $path): array
            {
                throw new \RuntimeException('Fallo recuperable de extracción de prueba.');
            }
        });

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-zip-exception-test.csv',
        ])->assertExitCode(1);

        $this->assertSame([], File::files($environment['temporary']));
    }

    public function test_extract_audit_preserves_a_previously_confirmed_association(): void
    {
        $dni = '05378290';
        $operation = '0002744794';
        $accountId = $this->createAccount($dni, $operation, 'AQP1', 'CTA-CONFIRMADA');
        $ccd = $this->createPrivateCcd($dni, 'AQP1', "DNI: {$dni}\nOperación: {$operation}");
        DB::table('ccd_cliente_cuenta')->insert([
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'revision_visual_pdf',
            'evidencia' => 'Confirmación previa de prueba.',
            'confirmado_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ccd:extract-audit', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-confirmed-preserved-test.csv',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'revision_visual_pdf',
        ]);
        $this->assertSame(1, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    private function createPrivateCcd(string $dni, string $cosecha, string $text): CcdCliente
    {
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
            'nombre_original' => 'CCD_'.$cosecha.'-'.Str::random(8).'.pdf',
            'verified_at' => now(),
        ]);

        return CcdCliente::create([
            'numdoc' => $dni,
            'pdf' => 'CCD_'.$cosecha.'-'.Str::random(8).'.pdf',
            'cosecha' => $cosecha,
            'link' => 'https://storage.kpinvest.pe/ccd/secure.pdf',
            'ccd_archivo_id' => $archivo->id,
        ]);
    }

    private function createZipOnlyCcd(string $dni, string $cosecha, string $pdf): CcdCliente
    {
        return CcdCliente::create([
            'numdoc' => $dni,
            'pdf' => $pdf,
            'cosecha' => $cosecha,
            'link' => 'https://storage.kpinvest.pe/ccd/legacy.zip',
        ]);
    }

    /** @return array{0:string,1:string} */
    private function createBbvaCandidates(string $dni): array
    {
        $first = '00110301995001707769';
        $second = '00110301925001679374';
        $this->createAccount($dni, $first, 'BBVA1', 'CTA-BBVA-PRIMERA');
        $this->createAccount($dni, $second, 'BBVA1', 'CTA-BBVA-SEGUNDA');

        return [$first, $second];
    }

    /** @return array{root:string,temporary:string} */
    private function configureZipV12(array $entries, ?int $maximumBytes = null, bool $createZip = true): array
    {
        $root = storage_path('framework/testing/ccd-zip-v12-'.Str::uuid());
        $temporary = $root.DIRECTORY_SEPARATOR.'temporary';
        File::ensureDirectoryExists($root, 0700, true);
        $this->temporaryDirectories[] = $root;

        if ($createZip) {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($root.DIRECTORY_SEPARATOR.'CONFIANZA1.zip', ZipArchive::CREATE) === true);

            foreach ($entries as $name => $contents) {
                $this->assertTrue($zip->addFromString($name, $contents));
            }

            $zip->close();
        }

        config([
            'ccd.zip_v12_source_provider' => \App\Services\Ccd\CcdZipV12FileSourceProvider::class,
            'ccd.zip_v12_root' => $root,
            'ccd.zip_v12_temp_root' => $temporary,
            'ccd.zip_v12_cosechas' => ['CONFIANZA1'],
            'ccd.max_file_bytes' => $maximumBytes ?? 52_428_800,
        ]);

        return compact('root', 'temporary');
    }

    private function createAccount(string $dni, string $operation, string $cosecha, string $account, string $entity = 'Caja AQP'): int
    {
        return (int) DB::table('clientes_cuentas')->insertGetId([
            'numdoc' => $dni,
            'cuenta' => $account,
            'nombre' => 'Cliente CCD Extractor',
            'dpto' => 'Arequipa',
            'operacion' => $operation,
            'entidad' => $entity,
            'producto' => 'Producto CCD',
            'cosecha' => $cosecha,
            'moneda' => 'PEN',
            'fecha_compra' => now()->subYear()->toDateString(),
            'fecha_castigo' => now()->subMonths(6)->toDateString(),
            'deuda_capital' => 1000,
            'interes' => 50,
            'deuda_total' => 1050,
            'direccion' => 'Dirección CCD',
            'provincia' => 'Arequipa',
            'distrito' => 'Arequipa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pdf(string $text): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml('<html><body><pre>'.e($text).'</pre></body></html>');
        $dompdf->render();

        return $dompdf->output();
    }

    private function writeTemporaryPdf(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ccd-zip-v12-');
        file_put_contents($path, $this->pdf($text));
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
