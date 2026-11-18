<?php

namespace Tests\Feature;

use App\Contracts\CcdZipV12PdfSourceProvider;
use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use App\Services\Ccd\CcdAutoAssociationApplyGuard;
use App\Services\Ccd\CcdPdfIdentifierExtractor;
use App\Services\Ccd\CcdVerifiedAssociationService;
use Dompdf\Dompdf;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_verified_association_command_rejects_apply_outside_the_exact_staging_database(): void
    {
        $dni = '80201928';
        $operation = '2744794';
        $this->createAccount($dni, $operation, 'AQP1', 'CTA-23303');
        $ccd = $this->createPrivateCcd($dni, 'AQP1', "DNI: {$dni}\nOperación: {$operation}");
        config(['ccd.auto_association_apply_enabled' => true]);

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--apply' => true,
            '--authorization' => 'CONFIRMAR_ASOCIACION_AUTOMATICA',
            '--output' => 'ccd-verified-blocked-database-test.csv',
        ])->assertExitCode(1);

        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_verified_association_service_confirms_once_and_is_idempotent_in_testing_fixture(): void
    {
        $dni = '80201928';
        $operation = '2744794';
        $accountId = $this->createAccount($dni, $operation, 'AQP1', 'CTA-23303');
        $ccd = $this->createPrivateCcd($dni, 'AQP1', "DNI: {$dni}\nOperación: {$operation}");
        $service = app(CcdVerifiedAssociationService::class);

        $first = $service->apply($ccd);
        $second = $service->apply($ccd->fresh());

        $this->assertSame('confirmada', $first['status']);
        $this->assertSame('ya_confirmada', $second['status']);
        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'operacion_pdf_automatica',
            'confirmado_por_user_id' => null,
        ]);
        $this->assertSame(1, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_apply_guard_requires_exact_staging_database_and_explicit_enablement(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getDatabaseName')->andReturn(CcdAutoAssociationApplyGuard::STAGING_DATABASE);
        $guard = app(CcdAutoAssociationApplyGuard::class);

        config(['ccd.auto_association_apply_enabled' => false]);
        $this->assertNotNull($guard->rejectionReason($connection));

        config(['ccd.auto_association_apply_enabled' => true]);
        $this->assertNull($guard->rejectionReason($connection));

        $production = Mockery::mock(ConnectionInterface::class);
        $production->shouldReceive('getDatabaseName')->andReturn('u480021566_produccion');
        $this->assertNotNull($guard->rejectionReason($production));
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

    public function test_verified_association_rejects_an_accidental_unlabelled_dni_reference(): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $this->createAccount($dni, $operation, 'BBVA1', 'CTA-BBVA-ACCIDENTAL', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "Referencia interna {$dni}\n{$operation}");

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-accidental-dni-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-accidental-dni-test.csv');
        $this->assertStringContainsString('dni_documental_no_corrobable', $csv);
        $this->assertStringContainsString('dni_etiqueta_documental_no_reconocida', $csv);
    }

    #[DataProvider('additionalDocumentaryDniFormats')]
    public function test_verified_association_accepts_additional_explicit_documentary_dni_formats(string $documentaryIdentity): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $this->createAccount($dni, $operation, 'BBVA1', 'CTA-BBVA-FORMATO', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "{$documentaryIdentity}\n{$operation}");
        $output = 'ccd-verified-dni-format-'.Str::lower(Str::random(8)).'.csv';

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => $output,
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/'.$output);
        $this->assertStringContainsString('elegible', $csv);
        $this->assertStringContainsString('dni_documental_etiquetado', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public static function additionalDocumentaryDniFormats(): array
    {
        return [
            'D.N.I.' => ['D.N.I.: 05378290'],
            'DNI N°' => ['DNI N° 05378290'],
            'DNI Nro.' => ['DNI Nro. 05378290'],
            'Nro. de documento' => ['Nro. de documento: 05378290'],
            'Número de documento' => ['Número de documento: 05378290'],
            'Documento de identidad' => ['Documento de identidad: 05378290'],
            'Documento Nacional de Identidad' => ['Documento Nacional de Identidad: 05378290'],
            'cero inicial con separadores' => ['D.N.I.: 05.378.290'],
            'etiqueta y valor en líneas separadas' => ["Número de\ndocumento:\n05378290"],
        ];
    }

    public function test_verified_association_distinguishes_unlabelled_absent_and_contradictory_documentary_dni(): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $this->createAccount($dni, $operation, 'BBVA1', 'CTA-BBVA-DIAGNOSTICO', 'BBVA');
        $cases = [
            'presente' => ["Código {$dni}\n{$operation}", 'dni_presente_sin_etiqueta_verificable'],
            'ausente' => ["Sin identidad documental\n{$operation}", 'dni_ausente_texto_extraido'],
            'incorrecto' => ["DNI: 87654321\n{$operation}", 'dni_documental_no_coincide'],
            'contradictorio' => ["DNI: {$dni}\nDocumento de identidad: 87654321\n{$operation}", 'dni_documental_contradictorio'],
            'incrustado' => ["DNI: 9{$dni}1\n{$operation}", 'dni_documental_no_coincide'],
            'parcial' => ["DNI: 5378290\n{$operation}", 'dni_documental_no_coincide'],
        ];

        foreach ($cases as $name => [$text, $expectedEvidence]) {
            $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', $text);
            $output = "ccd-verified-dni-diagnostic-{$name}-test.csv";

            $this->artisan('ccd:associate-verified', [
                '--ccd-id' => $ccd->id,
                '--output' => $output,
            ])->assertExitCode(0);

            $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/'.$output);
            $this->assertStringContainsString($expectedEvidence, $csv);
            $this->assertStringNotContainsString(',elegible,', $csv);
            $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
        }
    }

    public function test_verified_association_accepts_historical_bbva_recipient_heading_identity(): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $this->createAccount($dni, $operation, 'BBVA1', 'CTA-BBVA-DESTINATARIO', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "Estimado(a) Sr.(a): CLIENTE DE PRUEBA - {$dni}\n{$operation}");

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-recipient-dni-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-recipient-dni-test.csv');
        $this->assertStringContainsString('elegible', $csv);
        $this->assertStringContainsString('dni_destinatario_bbva_estimado_sr', $csv);
    }

    public function test_verified_association_accepts_historical_confianza_reference_recipient_identity_after_pdf_line_flattening(): void
    {
        $dni = '00003862';
        $operation = '6878399';
        $this->createAccount($dni, $operation, 'CONFIANZA', 'CTA-CONFIANZA-DNI', 'FINANCIERA CONFIANZA');
        $ccd = $this->createPrivateCcd(
            $dni,
            'CONFIANZA1',
            "Lima, 20 de mayo de 2014 Referencia: {$dni} Señor(a): CLIENTE DE PRUEBA\n{$operation}DEUDAACTUAL",
        );

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-confianza-recipient-dni-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-confianza-recipient-dni-test.csv');
        $this->assertStringContainsString('elegible', $csv);
        $this->assertStringContainsString('dni_destinatario_confianza_referencia_senor', $csv);
    }

    public function test_dni_recheck_command_compares_prior_reviews_without_writing_associations(): void
    {
        $eligibleDni = '05378290';
        $eligibleOperation = '00110301995001707769';
        $this->createAccount($eligibleDni, $eligibleOperation, 'BBVA1', 'CTA-RECHECK-ELEGIBLE', 'BBVA');
        $eligible = $this->createPrivateCcd($eligibleDni, 'BBVA_1_2', "D.N.I.: {$eligibleDni}\n{$eligibleOperation}");

        $reviewDni = '05378291';
        $reviewOperation = '00110301995001707770';
        $this->createAccount($reviewDni, $reviewOperation, 'BBVA1', 'CTA-RECHECK-REVISION', 'BBVA');
        $review = $this->createPrivateCcd($reviewDni, 'BBVA_1_2', "Código {$reviewDni}\n{$reviewOperation}");
        $prior = implode("\n", [
            'ccd_id,cliente_cuenta_id,operacion,fuente,resultado,motivo,evidencia,evidencia_dni,metodo_verificacion,modo',
            "{$eligible->id},,,privado,revision,dni_documental_no_corrobable,operacion_candidata_en_texto_pdf,dni_documental_no_corrobable,operacion_pdf_automatica,simulacion",
            "{$review->id},,,privado,revision,dni_documental_no_corrobable,operacion_candidata_en_texto_pdf,dni_documental_no_corrobable,operacion_pdf_automatica,simulacion",
        ])."\n";
        Storage::disk((string) config('ccd.private_disk'))->put('audits/ccd-prior-reviews-test.csv', $prior);

        $this->artisan('ccd:recheck-dni', [
            '--input' => ['ccd-prior-reviews-test.csv'],
            '--limit' => 10,
            '--output' => 'ccd-recheck-comparison-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-recheck-comparison-test.csv');
        $this->assertStringContainsString("{$eligible->id},revision,dni_documental_no_corrobable,elegible,ahora_elegible", $csv);
        $this->assertStringContainsString("{$review->id},revision,dni_documental_no_corrobable,revision,sigue_revision", $csv);
        $this->assertStringContainsString('dni_presente_sin_etiqueta_verificable', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereIn('ccd_cliente_id', [$eligible->id, $review->id])->count());
    }

    #[DataProvider('aqpValidationHarvests')]
    public function test_aqp_customer_code_is_detected_but_remains_blocked_for_automatic_association(string $cosecha, string $label): void
    {
        $dni = '00123456';
        $operation = $cosecha === 'AQP4' ? '4400123456' : '5500123456';
        $this->createAccount($dni, $operation, $cosecha, 'CTA-'.$cosecha);
        $ccd = $this->createPrivateCcd(
            $dni,
            $cosecha,
            "{$label}: {$dni}\n\nSEÑOR (A):\nCLIENTE DE PRUEBA\nOperación: {$operation}",
        );
        $output = 'ccd-aqp-candidate-'.Str::lower($cosecha).'-test.csv';

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => $output,
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/'.$output);
        $this->assertStringContainsString('revision', $csv);
        $this->assertStringContainsString('dni_codigo_cliente_aqp_pendiente_validacion_ampliada', $csv);
        $this->assertStringContainsString('dni_codigo_cliente_aqp_verificado', $csv);
        $this->assertStringNotContainsString(',elegible,', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());

        $applyResult = app(CcdVerifiedAssociationService::class)->apply($ccd->fresh());
        $this->assertSame('revision', $applyResult['status']);
        $this->assertSame('dni_codigo_cliente_aqp_pendiente_validacion_ampliada', $applyResult['reason']);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public static function aqpValidationHarvests(): array
    {
        return [
            'AQP4 con tilde' => ['AQP4', 'CÓDIGO DEL CLIENTE'],
            'AQP5 sin tilde' => ['AQP5', 'CODIGO DEL CLIENTE'],
        ];
    }

    #[DataProvider('unsafeAqpCustomerCodeCases')]
    public function test_unsafe_aqp_customer_codes_stay_in_review(string $documentaryHeader, string $expectedEvidence): void
    {
        $dni = '00123456';
        $operation = '4400123456';
        $this->createAccount($dni, $operation, 'AQP4', 'CTA-AQP4-UNSAFE');
        $ccd = $this->createPrivateCcd($dni, 'AQP4', "{$documentaryHeader}\nOperación: {$operation}");
        $output = 'ccd-aqp-unsafe-'.Str::lower(Str::random(8)).'.csv';

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => $output,
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/'.$output);
        $this->assertStringContainsString('revision', $csv);
        $this->assertStringContainsString($expectedEvidence, $csv);
        $this->assertStringNotContainsString(',elegible,', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public static function unsafeAqpCustomerCodeCases(): array
    {
        return [
            'código incorrecto' => ["CÓDIGO DEL CLIENTE: 87654321\nSEÑOR (A): CLIENTE", 'dni_codigo_cliente_aqp_no_coincide'],
            'código parcial' => ["CÓDIGO DEL CLIENTE: 0123456\nSEÑOR (A): CLIENTE", 'dni_codigo_cliente_aqp_no_coincide'],
            'código incrustado' => ["CÓDIGO DEL CLIENTE: 9001234561\nSEÑOR (A): CLIENTE", 'dni_codigo_cliente_aqp_no_coincide'],
            'dos códigos diferentes' => ["CÓDIGO DEL CLIENTE: 00123456\nSEÑOR (A): CLIENTE\nCÓDIGO DEL CLIENTE: 87654321\nSEÑOR (A): OTRO", 'dni_codigo_cliente_aqp_contradictorio'],
            'sin encabezado de destinatario' => ['Texto CÓDIGO DEL CLIENTE: 00123456 dentro del cuerpo.', 'dni_codigo_cliente_aqp_contexto_insuficiente'],
        ];
    }

    public function test_explicit_contradictory_dni_overrides_a_valid_aqp_customer_code(): void
    {
        $dni = '00123456';
        $operation = '4400123456';
        $this->createAccount($dni, $operation, 'AQP4', 'CTA-AQP4-DNI-CONTRARIO');
        $ccd = $this->createPrivateCcd(
            $dni,
            'AQP4',
            "CÓDIGO DEL CLIENTE: {$dni}\nSEÑOR (A): CLIENTE\nDNI: 87654321\nOperación: {$operation}",
        );

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-aqp-explicit-dni-conflict-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-aqp-explicit-dni-conflict-test.csv');
        $this->assertStringContainsString('revision', $csv);
        $this->assertStringContainsString('dni_documental_no_coincide', $csv);
        $this->assertStringNotContainsString(',elegible,', $csv);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_existing_explicit_dni_evidence_remains_authorized_in_an_aqp_document(): void
    {
        $dni = '00123456';
        $operation = '4400123456';
        $this->createAccount($dni, $operation, 'AQP4', 'CTA-AQP4-DNI-EXPLICITO');
        $ccd = $this->createPrivateCcd(
            $dni,
            'AQP4',
            "DNI: {$dni}\nCÓDIGO DEL CLIENTE: {$dni}\nSEÑOR (A): CLIENTE\nOperación: {$operation}",
        );

        $result = app(CcdVerifiedAssociationService::class)->analyze($ccd);

        $this->assertSame('elegible', $result['status']);
        $this->assertSame('dni_documental_etiquetado', $result['dni_evidence']);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->where('ccd_cliente_id', $ccd->id)->count());
    }

    public function test_aqp_customer_code_does_not_activate_for_other_harvest_or_ambiguous_operations(): void
    {
        $dni = '00123456';
        $operation = '3300123456';
        $this->createAccount($dni, $operation, 'AQP3', 'CTA-AQP3');
        $otherHarvest = $this->createPrivateCcd(
            $dni,
            'AQP3',
            "CÓDIGO DEL CLIENTE: {$dni}\nSEÑOR (A): CLIENTE\nOperación: {$operation}",
        );
        $otherResult = app(CcdVerifiedAssociationService::class)->analyzeForDniValidation($otherHarvest);
        $this->assertSame('revision', $otherResult['status']);
        $this->assertSame('dni_documental_no_corrobable', $otherResult['reason']);
        $this->assertNotSame('dni_codigo_cliente_aqp_verificado', $otherResult['dni_evidence']);

        $first = '4400123456';
        $second = '4400654321';
        $this->createAccount($dni, $first, 'AQP4', 'CTA-AQP4-A');
        $this->createAccount($dni, $second, 'AQP4', 'CTA-AQP4-B');
        $ambiguous = $this->createPrivateCcd(
            $dni,
            'AQP4',
            "CÓDIGO DEL CLIENTE: {$dni}\nSEÑOR (A): CLIENTE\n{$first}\n{$second}",
        );
        $ambiguousResult = app(CcdVerifiedAssociationService::class)->analyzeForDniValidation($ambiguous);
        $this->assertSame('revision', $ambiguousResult['status']);
        $this->assertStringContainsString('operaciones_candidatas', $ambiguousResult['evidence']);
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereIn('ccd_cliente_id', [$otherHarvest->id, $ambiguous->id])->count());
    }

    public function test_dni_recheck_measures_aqp_candidates_with_harvest_filter_without_writes(): void
    {
        $documents = [];

        foreach (['AQP4' => '4400123456', 'AQP5' => '5500123456', 'AQP3' => '3300123456'] as $cosecha => $operation) {
            $dni = '00123456';
            $this->createAccount($dni, $operation, $cosecha, 'CTA-RECHECK-'.$cosecha);
            $documents[$cosecha] = $this->createPrivateCcd(
                $dni,
                $cosecha,
                "CÓDIGO DEL CLIENTE: {$dni}\nSEÑOR (A): CLIENTE\nOperación: {$operation}",
            );
        }

        $conflictDni = '00123457';
        $conflictOperation = '4400123457';
        $this->createAccount($conflictDni, $conflictOperation, 'AQP4', 'CTA-RECHECK-AQP4-CONFLICT');
        $documents['AQP4-conflict'] = $this->createPrivateCcd(
            $conflictDni,
            'AQP4',
            "CÓDIGO DEL CLIENTE: 87654321\nSEÑOR (A): CLIENTE\nOperación: {$conflictOperation}",
        );

        $prior = [
            'ccd_id,cliente_cuenta_id,operacion,fuente,resultado,motivo,evidencia,evidencia_dni,metodo_verificacion,modo',
        ];

        foreach ($documents as $document) {
            $prior[] = "{$document->id},,,privado,revision,dni_documental_no_corrobable,operacion_documental_exacta,dni_documental_no_corrobable,operacion_pdf_automatica,simulacion";
        }

        Storage::disk((string) config('ccd.private_disk'))->put('audits/ccd-aqp-prior-reviews-test.csv', implode("\n", $prior)."\n");

        $this->artisan('ccd:recheck-dni', [
            '--input' => ['ccd-aqp-prior-reviews-test.csv'],
            '--cosecha' => ['AQP4', 'AQP5'],
            '--limit' => 10,
            '--output' => 'ccd-aqp-recheck-comparison-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-aqp-recheck-comparison-test.csv');
        $this->assertStringContainsString("{$documents['AQP4']->id},revision,dni_documental_no_corrobable,elegible,ahora_elegible", $csv);
        $this->assertStringContainsString("{$documents['AQP5']->id},revision,dni_documental_no_corrobable,elegible,ahora_elegible", $csv);
        $this->assertStringContainsString("{$documents['AQP4-conflict']->id},revision,dni_documental_no_corrobable,revision,contradiccion", $csv);
        $this->assertStringContainsString('dni_codigo_cliente_aqp_verificado', $csv);
        $this->assertStringContainsString('dni_codigo_cliente_aqp_no_coincide', $csv);
        $this->assertStringNotContainsString("\n{$documents['AQP3']->id},", "\n{$csv}");
        $this->assertSame(0, DB::table('ccd_cliente_cuenta')->whereIn('ccd_cliente_id', collect($documents)->pluck('id'))->count());
    }

    public function test_verified_association_keeps_a_manual_confirmation_for_the_same_operation_when_identity_is_not_reparseable(): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $accountId = $this->createAccount($dni, $operation, 'BBVA1', 'CTA-MANUAL-SAME', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "Resumen {$operation}");
        DB::table('ccd_cliente_cuenta')->insert([
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'revision_visual_pdf',
            'evidencia' => 'Confirmación manual previa.',
            'confirmado_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-manual-same-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-manual-same-test.csv');
        $this->assertStringContainsString('ya_confirmada', $csv);
        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'metodo_verificacion' => 'revision_visual_pdf',
        ]);
    }

    public function test_verified_association_reports_conflict_when_an_explicit_document_dni_contradicts_a_manual_confirmation(): void
    {
        $dni = '05378290';
        $operation = '00110301995001707769';
        $accountId = $this->createAccount($dni, $operation, 'BBVA1', 'CTA-MANUAL-CONTRADICTORY', 'BBVA');
        $ccd = $this->createPrivateCcd($dni, 'BBVA_1_2', "DNI: 87654321\n{$operation}");
        DB::table('ccd_cliente_cuenta')->insert([
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'estado' => 'confirmada',
            'metodo_verificacion' => 'revision_visual_pdf',
            'evidencia' => 'Confirmación manual previa.',
            'confirmado_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ccd:associate-verified', [
            '--ccd-id' => $ccd->id,
            '--output' => 'ccd-verified-manual-dni-conflict-test.csv',
        ])->assertExitCode(0);

        $csv = Storage::disk((string) config('ccd.private_disk'))->get('audits/ccd-verified-manual-dni-conflict-test.csv');
        $this->assertStringContainsString('conflicto', $csv);
        $this->assertStringContainsString('dni_documental_contradice_confirmacion_existente', $csv);
        $this->assertDatabaseHas('ccd_cliente_cuenta', [
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $accountId,
            'metodo_verificacion' => 'revision_visual_pdf',
        ]);
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
        $this->assertStringContainsString('dni_no_evaluable_sin_texto', $textlessCsv);
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

        config(['ccd.zip_v12_source_provider' => new class($pdfPath) implements CcdZipV12PdfSourceProvider
        {
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
        app()->bind(CcdPdfIdentifierExtractor::class, static fn (): CcdPdfIdentifierExtractor => new class extends CcdPdfIdentifierExtractor
        {
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
            $zip = new ZipArchive;
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
        $dompdf = new Dompdf;
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
