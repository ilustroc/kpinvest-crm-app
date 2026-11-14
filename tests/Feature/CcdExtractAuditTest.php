<?php

namespace Tests\Feature;

use App\Contracts\CcdZipV12PdfSourceProvider;
use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CcdExtractAuditTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int,string> */
    private array $temporaryFiles = [];

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

    private function createAccount(string $dni, string $operation, string $cosecha, string $account): void
    {
        DB::table('clientes_cuentas')->insert([
            'numdoc' => $dni,
            'cuenta' => $account,
            'nombre' => 'Cliente CCD Extractor',
            'dpto' => 'Arequipa',
            'operacion' => $operation,
            'entidad' => 'Caja AQP',
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
