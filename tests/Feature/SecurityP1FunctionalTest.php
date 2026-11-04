<?php

namespace Tests\Feature;

use App\Exports\CnaExport;
use App\Models\CnaSolicitud;
use App\Models\PromesaPago;
use App\Models\User;
use App\Services\CnaReportService;
use App\Support\ExportSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityP1FunctionalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Limpiar tablas de pruebas en base aislada kp_crm_testing
        DB::table('users')->delete();
        DB::table('promesa_cuotas')->delete();
        DB::table('promesa_operaciones')->delete();
        DB::table('promesas_pago')->delete();
        DB::table('cna_solicitudes')->delete();
        DB::table('pagos')->delete();
        DB::table('clientes_cuentas')->delete();
    }

    public function test_promesa_creation_rejects_operations_not_belonging_to_client(): void
    {
        $asesor = User::create([
            'name' => 'Asesor Test',
            'email' => 'asesor@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        // Insertar cuenta para Cliente A y cuenta para Cliente B
        DB::table('clientes_cuentas')->insert([
            [
                'numdoc' => '11111111',
                'nombre' => 'Cliente A',
                'operacion' => 'OP-A1',
                'entidad' => 'BANCO A',
                'producto' => 'PRESTAMO',
                'cosecha' => '2023',
                'cuenta' => 'CTA-A1',
                'deuda_capital' => 1000.00,
                'deuda_total' => 1200.00,
            ],
            [
                'numdoc' => '22222222',
                'nombre' => 'Cliente B',
                'operacion' => 'OP-B1',
                'entidad' => 'BANCO B',
                'producto' => 'PRESTAMO',
                'cosecha' => '2023',
                'cuenta' => 'CTA-B1',
                'deuda_capital' => 2000.00,
                'deuda_total' => 2400.00,
            ],
        ]);

        $this->actingAs($asesor);

        // Intento de asociar la operación OP-B1 al Cliente A (DNI 11111111)
        $response = $this->post(route('clientes.promesas.store', '11111111'), [
            'tipo' => 'cancelacion',
            'telefono' => '999888777',
            'fecha_pago' => now()->addDays(5)->format('Y-m-d'),
            'monto_cancel' => 500.00,
            'operaciones' => ['OP-B1'],
        ]);

        $response->assertSessionHasErrors('operaciones');
        $this->assertDatabaseCount('promesas_pago', 0);
    }

    public function test_promesa_creation_succeeds_when_operations_belong_to_client(): void
    {
        $asesor = User::create([
            'name' => 'Asesor Test',
            'email' => 'asesor@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        DB::table('clientes_cuentas')->insert([
            'numdoc' => '11111111',
            'nombre' => 'Cliente A',
            'operacion' => 'OP-A1',
            'entidad' => 'BANCO A',
            'producto' => 'PRESTAMO',
            'cosecha' => '2023',
            'cuenta' => 'CTA-A1',
            'deuda_capital' => 1000.00,
            'deuda_total' => 1200.00,
        ]);

        $this->actingAs($asesor);

        $response = $this->post(route('clientes.promesas.store', '11111111'), [
            'tipo' => 'cancelacion',
            'telefono' => '999888777',
            'fecha_pago' => now()->addDays(5)->format('Y-m-d'),
            'monto_cancel' => 500.00,
            'operaciones' => ['OP-A1'],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('promesas_pago', [
            'dni' => '11111111',
            'operacion' => 'OP-A1',
            'workflow_estado' => 'pendiente',
        ]);
    }

    public function test_cna_creation_rejects_operations_not_belonging_to_client(): void
    {
        $asesor = User::create([
            'name' => 'Asesor Test',
            'email' => 'asesor@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        DB::table('clientes_cuentas')->insert([
            'numdoc' => '11111111',
            'nombre' => 'Cliente A',
            'operacion' => 'OP-A1',
            'entidad' => 'CAJA AREQUIPA',
            'producto' => 'PRESTAMO',
            'cosecha' => 'CAJAAQP4',
            'cuenta' => 'CTA-A1',
            'deuda_capital' => 1000.00,
            'deuda_total' => 1200.00,
        ]);

        $this->actingAs($asesor);

        $response = $this->post(route('clientes.cna.store', '11111111'), [
            'fecha_pago_realizado' => now()->subDays(2)->format('Y-m-d'),
            'monto_pagado' => 450.00,
            'operaciones' => ['OP-INEXISTENTE-999'],
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('cna_solicitudes', 0);
    }

    public function test_export_sanitizer_neutralizes_formula_injection_characters(): void
    {
        // Fórmulas y caracteres peligrosos antepuestos con apóstrofe (')
        $this->assertSame("'=1+1", ExportSanitizer::sanitize('=1+1'));
        $this->assertSame("'+CMD", ExportSanitizer::sanitize('+CMD'));
        $this->assertSame("'-5*2", ExportSanitizer::sanitize('-5*2'));
        $this->assertSame("'@SUM(A1:A5)", ExportSanitizer::sanitize('@SUM(A1:A5)'));
        $this->assertSame("'\tTAB", ExportSanitizer::sanitize("\tTAB"));
        $this->assertSame("'\rRETURN", ExportSanitizer::sanitize("\rRETURN"));
        $this->assertSame("' =2+2", ExportSanitizer::sanitize(' =2+2'));

        // Cadenas normales y legítimas permanecen intactas
        $this->assertSame('CARLOS LOPEZ', ExportSanitizer::sanitize('CARLOS LOPEZ'));
        $this->assertSame('CAJA AREQUIPA', ExportSanitizer::sanitize('CAJA AREQUIPA'));
        $this->assertSame('12345678', ExportSanitizer::sanitize('12345678'));

        // Números primitivos permanecen intactos como números
        $this->assertSame(1234.56, ExportSanitizer::sanitize(1234.56));
        $this->assertSame(100, ExportSanitizer::sanitize(100));
        $this->assertNull(ExportSanitizer::sanitize(null));
    }

    public function test_cna_export_runs_without_ambiguous_column_id_sql_error(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'administrador',
            'active' => 1,
        ]);

        DB::table('clientes_cuentas')->insert([
            'numdoc' => '44444444',
            'nombre' => 'Titular CNA',
            'operacion' => 'OP-CNA-01',
            'entidad' => 'CAJA AREQUIPA',
            'producto' => 'CONSUMO',
            'cosecha' => 'CAJAAQP4',
            'cuenta' => 'CTA-01',
            'deuda_capital' => 5000.00,
            'deuda_total' => 6000.00,
        ]);

        CnaSolicitud::create([
            'dni' => '44444444',
            'titular' => 'Titular CNA',
            'operaciones' => ['OP-CNA-01'],
            'nro_carta' => '0001-2026',
            'correlativo' => 1,
            'producto' => 'CONSUMO',
            'monto_pagado' => 2500.00,
            'fecha_pago_realizado' => now()->toDateString(),
            'workflow_estado' => 'aprobada',
            'user_id' => $admin->id,
            'aprobado_por' => $admin->id,
            'aprobado_at' => now(),
        ]);

        $service = app(CnaReportService::class);
        $query = $service->getFilteredQuery([]);

        // Instanciar exportador y ejecutar exportación a archivo temporal
        $export = new CnaExport($query);
        $tmpPath = $export->export();

        $this->assertFileExists($tmpPath);
        $this->assertGreaterThan(0, filesize($tmpPath));

        @unlink($tmpPath);
    }

    public function test_unapproved_cna_document_download_is_forbidden(): void
    {
        $user = User::create([
            'name' => 'Asesor User',
            'email' => 'asesor@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        $cna = CnaSolicitud::create([
            'dni' => '55555555',
            'titular' => 'Titular Pendiente',
            'operaciones' => ['OP-PEND'],
            'nro_carta' => '0002-2026',
            'correlativo' => 2,
            'monto_pagado' => 1000.00,
            'fecha_pago_realizado' => now()->toDateString(),
            'workflow_estado' => 'pendiente', // NO aprobada
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);

        // Intentar descargar CNA en estado pendiente
        $response = $this->get(route('cna.pdf', $cna->id));
        $response->assertStatus(403);
    }

    public function test_promesa_and_cna_documents_never_leak_debts_of_another_client_with_same_operation(): void
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'administrador',
            'active' => 1,
        ]);

        // Dos clientes distintos tienen casualmente el mismo número de operación 'OP-SHARED-01'
        DB::table('clientes_cuentas')->insert([
            [
                'numdoc' => '11111111',
                'nombre' => 'Titular Legítimo A',
                'operacion' => 'OP-SHARED-01',
                'entidad' => 'BANCO EXCLUSIVO A',
                'producto' => 'PRESTAMO PERSONAL',
                'cosecha' => 'CAJAAQP4',
                'cuenta' => 'CTA-LEGITIMA-A',
                'deuda_capital' => 1000.00,
                'deuda_total' => 1200.00,
            ],
            [
                'numdoc' => '22222222',
                'nombre' => 'Titular Invasor B',
                'operacion' => 'OP-SHARED-01',
                'entidad' => 'ENTIDAD AJENA B',
                'producto' => 'TARJETA ORO',
                'cosecha' => 'CAJAAQP3',
                'cuenta' => 'CTA-AJENA-B',
                'deuda_capital' => 99999.00,
                'deuda_total' => 99999.00,
            ],
        ]);

        // Promesa para Titular A
        $promesaA = PromesaPago::create([
            'dni' => '11111111',
            'operacion' => 'OP-SHARED-01',
            'tipo' => 'cancelacion',
            'monto' => 800.00,
            'workflow_estado' => 'aprobada',
            'user_id' => $admin->id,
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->toDateString(),
        ]);

        $promesaService = app(\App\Services\Promesa\PromesaDocumentService::class);
        $reflection = new \ReflectionClass($promesaService);
        $method = $reflection->getMethod('operationRows');
        $method->setAccessible(true);

        $opRows = $method->invoke($promesaService, ['OP-SHARED-01'], $promesaA);

        // Verificar que la entidad y el monto de deuda provengan estrictamente de Titular A
        $this->assertSame('BANCO EXCLUSIVO A', $opRows->entity);
        $this->assertCount(1, $opRows->table);
        $this->assertSame('1,200.00', $opRows->table[0]['deuda_total']);
        $this->assertNotSame('ENTIDAD AJENA B', $opRows->entity);
        $this->assertNotSame('99,999.00', $opRows->table[0]['deuda_total']);
    }

    public function test_real_xlsx_exports_neutralize_formula_injection_and_preserve_numeric_cells(): void
    {
        // 1. Probar PaymentExport con celdas de inyección maliciosas
        DB::table('pagos')->insert([
            'numdoc' => '12345678',
            'nombre_cliente' => '=cmd|\'/c calc\'!A0',
            'operacion' => '+OP-1002',
            'monto_pagado' => 1450.75,
            'gestor' => '@GESTOR_ADM',
            'cosecha' => "\tCOSECHA_TAB",
            'cuenta_recaudo' => "\rRECAUDO_CR",
            'entidad' => '-BANCO_NEG',
            'fecha' => '2026-10-15',
            'lote_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentQuery = DB::table('pagos')->where('numdoc', '12345678');
        $paymentExport = new \App\Exports\PaymentExport($paymentQuery);
        $paymentFilePath = $paymentExport->export();

        $this->assertFileExists($paymentFilePath);

        // Leer el archivo XLSX real con PhpSpreadsheet
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($paymentFilePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Fila 1 = encabezados, Fila 2 = datos
        $nombreCell = $sheet->getCell('C2');
        $montoCell = $sheet->getCell('E2');
        $gestorCell = $sheet->getCell('F2');
        $entidadCell = $sheet->getCell('I2');

        // La inyección debe estar neutralizada: isFormula() debe ser FALSE
        $this->assertFalse($nombreCell->isFormula());
        $this->assertFalse($gestorCell->isFormula());
        $this->assertFalse($entidadCell->isFormula());

        // El contenido textual debe comenzar con apóstrofe para forzar texto literal
        $this->assertSame("'=cmd|'/c calc'!A0", $nombreCell->getValue());
        $this->assertSame("'@GESTOR_ADM", $gestorCell->getValue());
        $this->assertSame("'-BANCO_NEG", $entidadCell->getValue());

        // La celda de monto DEBE ser numérica (no texto) y conservar su valor exacto
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC, $montoCell->getDataType());
        $this->assertSame(1450.75, (float) $montoCell->getValue());

        @unlink($paymentFilePath);

        // Verificar que los datos originales en MariaDB sigan intactos y sin apóstrofes
        $dbPago = DB::table('pagos')->where('numdoc', '12345678')->first();
        $this->assertSame('=cmd|\'/c calc\'!A0', $dbPago->nombre_cliente);
        $this->assertSame(1450.75, (float) $dbPago->monto_pagado);
    }
}
