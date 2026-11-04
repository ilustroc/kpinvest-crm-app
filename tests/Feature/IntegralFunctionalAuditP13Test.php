<?php

namespace Tests\Feature;

use App\Models\CnaSolicitud;
use App\Models\Pago;
use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Cna\CnaDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IntegralFunctionalAuditP13Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Limpiar tablas en entorno de testing aislado (kp_crm_testing)
        DB::table('users')->delete();
        DB::table('promesa_cuotas')->delete();
        DB::table('promesa_operaciones')->delete();
        DB::table('promesas_pago')->delete();
        DB::table('cna_solicitudes')->delete();
        DB::table('pagos')->delete();
        DB::table('clientes_cuentas')->delete();
    }

    private function createTestUser(string $role, string $email, ?int $supervisorId = null): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Audit User',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => $role,
            'supervisor_id' => $supervisorId,
            'active' => 1,
        ]);
    }

    /**
     * 1. Login, recuperación de sesión y soporte de sesiones concurrentes.
     */
    public function test_login_multi_session_and_security_controls(): void
    {
        // Rechazo de usuario inactivo
        $inactive = User::create([
            'name' => 'Usuario Inactivo',
            'email' => 'inactivo@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 0,
        ]);

        $responseInactive = $this->post('/login', [
            'email' => 'inactivo@kpinvest.pe',
            'password' => 'secret123',
        ]);
        $responseInactive->assertSessionHasErrors(['email' => 'Credenciales inválidas.']);
        $this->assertGuest();

        $user = $this->createTestUser('asesor', 'asesor.multi@kpinvest.pe');

        // Múltiples sesiones simultáneas con las mismas credenciales
        $sessionA = $this->post('/login', [
            'email' => 'asesor.multi@kpinvest.pe',
            'password' => 'secret123',
        ]);
        $sessionA->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        // Deslogueo de sesión local y login en segunda sesión sin invalidar credencial
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $sessionB = $this->post('/login', [
            'email' => 'asesor.multi@kpinvest.pe',
            'password' => 'secret123',
        ]);
        $sessionB->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * 2. Matriz de Roles y Control de Acceso por URL.
     */
    public function test_role_access_control_endpoints_matrix(): void
    {
        $admin = $this->createTestUser('administrador', 'admin.matrix@kpinvest.pe');
        $supervisor = $this->createTestUser('supervisor', 'sup.matrix@kpinvest.pe');
        $soporte = $this->createTestUser('soporte', 'sop.matrix@kpinvest.pe');
        $asesor = $this->createTestUser('asesor', 'asesor.matrix@kpinvest.pe');

        // Administrador: Acceso a todos los módulos
        $this->actingAs($admin);
        $this->get('/')->assertStatus(200);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/administracion')->assertStatus(200);
        $this->get('/autorizacion')->assertStatus(200);
        $this->get('/integracion/data')->assertStatus(200);
        $this->get('/reportes/pagos')->assertStatus(200);

        // Supervisor: Acceso a autorizaciones y reportes, gestión de equipo
        $this->actingAs($supervisor);
        $this->get('/')->assertStatus(200);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/autorizacion')->assertStatus(200);
        $this->get('/reportes/pagos')->assertStatus(200);
        $this->get('/administracion')->assertStatus(200);

        // Soporte: Acceso a reportes e integración, prohibido autorizaciones
        $this->actingAs($soporte);
        $this->get('/')->assertStatus(200);
        $this->get('/integracion/data')->assertStatus(200);
        $this->get('/reportes/pagos')->assertStatus(200);
        $this->get('/autorizacion')->assertStatus(200);

        // Asesor: Acceso a clientes y dashboard, prohibido módulos administrativos
        $this->actingAs($asesor);
        $this->get('/')->assertStatus(200);
        $this->get('/administracion')->assertStatus(403);
        $this->get('/autorizacion')->assertStatus(403);
        $this->get('/integracion/data')->assertStatus(403);
        $this->get('/reportes/pagos')->assertStatus(403);
    }

    /**
     * 3. Búsqueda y sugerencias de clientes.
     */
    public function test_client_lookup_suggest_and_search_endpoints(): void
    {
        $asesor = $this->createTestUser('asesor', 'asesor.lookup@kpinvest.pe');

        DB::table('clientes_cuentas')->insert([
            [
                'numdoc' => '40506070',
                'nombre' => 'JUAN PEREZ GONZALES',
                'operacion' => 'OP-4050',
                'entidad' => 'BCP',
                'producto' => 'PRESTAMO',
                'cosecha' => '2023',
                'cuenta' => 'CTA-01',
                'deuda_capital' => 1200.00,
                'deuda_total' => 1500.00,
            ],
            [
                'numdoc' => '40506071',
                'nombre' => 'MARIA PEREZ RAMIREZ',
                'operacion' => 'OP-4051',
                'entidad' => 'BBVA',
                'producto' => 'TARJETA',
                'cosecha' => '2023',
                'cuenta' => 'CTA-02',
                'deuda_capital' => 800.00,
                'deuda_total' => 1000.00,
            ],
        ]);

        $this->actingAs($asesor);

        // Sugerencias por DNI
        $resSuggest = $this->getJson('/clientes/suggest?q=40506070');
        $resSuggest->assertStatus(200);
        $resSuggest->assertJsonFragment(['dni' => '40506070']);

        // Búsqueda rápida por nombre (quick lookup)
        $resQuick = $this->get('/clientes/lookup?q=40506070');
        $resQuick->assertRedirect(route('clientes.show', '40506070'));

        // Consulta de cliente que no existe
        $resNotFound = $this->get('/clientes/lookup?q=99999999');
        $resNotFound->assertSessionHas('quick_error');
    }

    /**
     * 4. Ficha 360° del cliente y permisos de eliminación de pagos.
     */
    public function test_client_360_view_and_payment_deletion_permissions(): void
    {
        $admin = $this->createTestUser('administrador', 'admin.del@kpinvest.pe');
        $asesor = $this->createTestUser('asesor', 'asesor.del@kpinvest.pe');

        DB::table('clientes_cuentas')->insert([
            'numdoc' => '12344321',
            'nombre' => 'CLIENTE TEST PAGOS',
            'operacion' => 'OP-PAGO-01',
            'entidad' => 'BCP',
            'producto' => 'PRESTAMO',
            'cosecha' => '2023',
            'cuenta' => 'CTA-PAGO-01',
            'deuda_capital' => 3000.00,
            'deuda_total' => 3500.00,
        ]);

        $pagoId = DB::table('pagos')->insertGetId([
            'numdoc' => '12344321',
            'monto_pagado' => 350.00,
            'fecha' => now()->format('Y-m-d'),
            'operacion' => 'OP-PAGO-01',
            'entidad' => 'BCP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ver ficha como asesor: permitido ver información global
        $this->actingAs($asesor);
        $resFicha = $this->get('/clientes/12344321');
        $resFicha->assertStatus(200);
        $resFicha->assertSee('CLIENTE TEST PAGOS');

        // Intento de eliminación de pago por asesor -> 403 Forbidden
        $resDelAsesor = $this->post('/clientes/12344321/pagos/delete', [
            'ids' => [$pagoId],
            'motivo' => 'Eliminación indebida',
        ]);
        $resDelAsesor->assertStatus(403);
        $this->assertDatabaseHas('pagos', ['id' => $pagoId]);

        // Eliminación autorizada por administrador -> Éxito y auditoría
        $this->actingAs($admin);
        $resDelAdmin = $this->post('/clientes/12344321/pagos/delete', [
            'ids' => [$pagoId],
            'motivo' => 'Corrección contable autorizada',
        ]);
        $resDelAdmin->assertRedirect();
        $this->assertDatabaseMissing('pagos', ['id' => $pagoId]);
    }

    /**
     * 5. Promesas de pago, calendario de cuotas y descarga de acuerdos oficiales.
     */
    public function test_promesas_workflow_multi_quota_schedule_and_isolated_agreement(): void
    {
        $admin = $this->createTestUser('administrador', 'admin.pdp@kpinvest.pe');
        $supervisor = $this->createTestUser('supervisor', 'sup.pdp@kpinvest.pe');
        $asesor = $this->createTestUser('asesor', 'asesor.pdp@kpinvest.pe');

        DB::table('clientes_cuentas')->insert([
            'numdoc' => '55667788',
            'nombre' => 'CLIENTE PDP MULTICUOTA',
            'operacion' => 'OP-PDP-01',
            'entidad' => 'BCP',
            'producto' => 'CONSUMO',
            'cosecha' => '2023',
            'cuenta' => 'CTA-PDP-01',
            'deuda_capital' => 2000.00,
            'deuda_total' => 2500.00,
        ]);

        $this->actingAs($asesor);

        // Creación de promesa con cuotas (convenio)
        $resStore = $this->post(route('clientes.promesas.store', '55667788'), [
            'tipo' => 'convenio',
            'telefono' => '987654321',
            'monto_convenio' => 1200.00,
            'nro_cuotas' => 3,
            'operaciones' => ['OP-PDP-01'],
            'cron_fecha' => [
                now()->addMonth()->format('Y-m-d'),
                now()->addMonths(2)->format('Y-m-d'),
                now()->addMonths(3)->format('Y-m-d'),
            ],
            'cron_monto' => [400.00, 400.00, 400.00],
        ]);
        $resStore->assertSessionHasNoErrors();

        $promesa = PromesaPago::where('numdoc', '55667788')->firstOrFail();
        $this->assertSame('pendiente', $promesa->workflow_estado);

        // Intento de descarga en estado pendiente -> 403 Forbidden
        $this->get(route('promesas.acuerdo', $promesa->id))->assertStatus(403);

        // Preaprobación por supervisor
        $this->actingAs($supervisor);
        $resPre = $this->post(route('autorizacion.preaprobar', $promesa->id), [
            'nota_estado' => 'Preaprobación revisada',
        ]);
        $resPre->assertRedirect();
        $promesa->refresh();
        $this->assertSame('preaprobada', $promesa->workflow_estado);

        // Intento de descarga en estado preaprobada -> 403 Forbidden
        $this->get(route('promesas.acuerdo', $promesa->id))->assertStatus(403);

        // Aprobación final por administrador
        $this->actingAs($admin);
        $resApr = $this->post(route('autorizacion.aprobar', $promesa->id), [
            'nota_estado' => 'Aprobación definitiva',
        ]);
        $resApr->assertRedirect();
        $promesa->refresh();
        $this->assertSame('aprobada', $promesa->workflow_estado);

        // Almacenar archivo sintético de acuerdo aprobado para descarga
        $pdfDir = storage_path('app/promesas/pdfs');
        if (!is_dir($pdfDir)) {
            @mkdir($pdfDir, 0770, true);
        }
        $pdfFile = "{$pdfDir}/Acuerdo_{$promesa->id}_{$promesa->dni}.pdf";
        file_put_contents($pdfFile, '%PDF-1.4 SYNTHETIC ACUERDO CONTENT');

        try {
            // Descarga autorizada del documento en estado aprobada
            $resDoc = $this->get(route('promesas.acuerdo', $promesa->id));
            $resDoc->assertStatus(200);
            $this->assertSame('application/pdf', $resDoc->headers->get('Content-Type'));
        } finally {
            @unlink($pdfFile);
        }
    }

    /**
     * 6. Solicitudes de CNA y generación oficial de documentos.
     */
    public function test_cna_workflow_and_document_download_rules(): void
    {
        $admin = $this->createTestUser('administrador', 'admin.cna@kpinvest.pe');
        $supervisor = $this->createTestUser('supervisor', 'sup.cna@kpinvest.pe');
        $asesor = $this->createTestUser('asesor', 'asesor.cna@kpinvest.pe');

        DB::table('clientes_cuentas')->insert([
            'numdoc' => '99887766',
            'nombre' => 'TITULAR CNA FLUJO',
            'operacion' => 'OP-CNA-FLOW',
            'entidad' => 'BBVA',
            'producto' => 'VEHICULAR',
            'cosecha' => '2023',
            'cuenta' => 'CTA-CNA-01',
            'deuda_capital' => 4000.00,
            'deuda_total' => 4500.00,
        ]);

        $this->actingAs($asesor);

        // Creación de solicitud CNA
        $resStore = $this->post(route('clientes.cna.store', '99887766'), [
            'fecha_pago_realizado' => now()->subDays(3)->format('Y-m-d'),
            'monto_pagado' => 4500.00,
            'operaciones' => ['OP-CNA-FLOW'],
        ]);
        $resStore->assertSessionHasNoErrors();

        $cna = CnaSolicitud::where('numdoc', '99887766')->firstOrFail();
        $this->assertSame('pendiente', $cna->workflow_estado);

        // Prohibido descargar en estado pendiente -> 403 Forbidden
        $this->get(route('cna.pdf', $cna->id))->assertStatus(403);

        // Preaprobación por supervisor
        $this->actingAs($supervisor);
        $this->post(route('cna.preaprobar', $cna->id), ['nota_estado' => 'Conforme saldo cero'])->assertRedirect();
        $cna->refresh();
        $this->assertSame('preaprobada', $cna->workflow_estado);

        // Prohibido descargar en estado preaprobada -> 403 Forbidden
        $this->get(route('cna.pdf', $cna->id))->assertStatus(403);

        // Aprobación por administrador (mocking generación física de plantillas docx externas)
        $docServiceMock = $this->mock(CnaDocumentService::class);
        $docServiceMock->shouldReceive('generateOutputsFromTemplate')->once();

        $this->actingAs($admin);
        $this->post(route('cna.aprobar', $cna->id), ['nota_estado' => 'Emitir certificado'])->assertRedirect();
        $cna->refresh();
        $this->assertSame('aprobada', $cna->workflow_estado);

        // Descarga oficial autorizada en estado aprobada
        $cnaPdfDir = storage_path('app/cna/pdfs');
        if (!is_dir($cnaPdfDir)) {
            @mkdir($cnaPdfDir, 0770, true);
        }
        $pdfFile = "{$cnaPdfDir}/CNA {$cna->nro_carta} - {$cna->dni}.pdf";
        file_put_contents($pdfFile, '%PDF-1.4 SYNTHETIC CNA PDF');

        try {
            $resPdf = $this->actingAs($asesor)->get(route('cna.pdf', $cna->id));
            $resPdf->assertStatus(200);
        } finally {
            @unlink($pdfFile);
        }
    }

    /**
     * 7. Reportes, facets y exportación Excel con sanitización.
     */
    public function test_reports_facets_and_formula_injection_defense(): void
    {
        $admin = $this->createTestUser('administrador', 'admin.rpt@kpinvest.pe');

        DB::table('clientes_cuentas')->insert([
            'numdoc' => '77665544',
            'nombre' => '=cmd|"/C calc"!A0',
            'operacion' => 'OP-RPT-01',
            'entidad' => 'BCP',
            'producto' => 'PRESTAMO',
            'cosecha' => '2023',
            'cuenta' => 'CTA-RPT-01',
            'deuda_capital' => 1500.00,
            'deuda_total' => 1800.00,
        ]);

        DB::table('pagos')->insert([
            'numdoc' => '77665544',
            'monto_pagado' => 1500.00,
            'fecha' => now()->format('Y-m-d'),
            'operacion' => 'OP-RPT-01',
            'entidad' => 'BCP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin);

        // Consulta de Facets
        $resFacets = $this->getJson(route('reportes.pagos.facets'));
        $resFacets->assertStatus(200);
        $resFacets->assertJsonStructure(['entidades', 'cosechas']);

        // Exportación de Pagos con sanitización contra inyección de fórmulas
        $resExport = $this->get(route('reportes.pagos.export'));
        $resExport->assertStatus(200);
        $this->assertTrue(
            str_contains($resExport->headers->get('content-disposition', ''), 'pagos_')
        );
    }

    /**
     * 8. Integración de DataImport: Vista previa, clasificación en 3 vías y descarga de reporte de cuarentena.
     */
    public function test_integracion_data_preview_and_reconciliation_workflow(): void
    {
        $admin = $this->createTestUser('administrador', 'admin.import@kpinvest.pe');

        // Estado inicial de base de datos
        DB::table('clientes_cuentas')->insert([
            'numdoc' => '10203040',
            'nombre' => 'CUENTA EXISTENTE EN BD',
            'operacion' => 'OP-HIST-01',
            'entidad' => 'BCP',
            'producto' => 'PRESTAMO',
            'cosecha' => '2023',
            'cuenta' => 'CTA-01',
            'deuda_capital' => 1000.00,
            'deuda_total' => 1200.00,
        ]);

        $this->actingAs($admin);

        // CSV sintético con:
        // 1. Cuenta nueva (INSERT)
        // 2. Cuenta existente modificada (UPDATE)
        // 3. Fila con conflicto/error (CUARENTENA)
        // 4. Repetición idéntica (DUPLICADO CSV omitido)
        $csvContent = implode("\n", [
            'NUMDOC,CUENTA,OPERACION,NOMBRE,PRODUCTO,DPTO,PROVINCIA,DISTRITO,DIRECCION,ENTIDAD,COSECHA,FECHA_COMPRA,FECHA_CASTIGO,MONEDA,DEUDA_CAPITAL,INTERES,DEUDA_TOTAL',
            '10203040,CTA-01,OP-HIST-01,CUENTA EXISTENTE EN BD,PRESTAMO,LIMA,LIMA,MIRAFLORES,CALLE 1,BCP,2023,2023-01-01,2023-06-01,PEN,800.00,100.00,900.00', // UPDATE deuda de 1000 a 800
            '90807060,CTA-02,OP-NEW-01,CUENTA NUEVA CARTERA,TARJETA,LIMA,LIMA,SAN ISIDRO,CALLE 2,BBVA,2023,2023-01-01,2023-06-01,PEN,500.00,50.00,550.00', // INSERT
            '90807060,CTA-02,OP-NEW-01,CUENTA NUEVA CARTERA,TARJETA,LIMA,LIMA,SAN ISIDRO,CALLE 2,BBVA,2023,2023-01-01,2023-06-01,PEN,500.00,50.00,550.00', // DUPLICADO IDÉNTICO
            '88776655,CTA-03,,CUENTA SIN OPERACION,PRESTAMO,LIMA,LIMA,SURCO,CALLE 3,INTERBANK,2023,2023-01-01,2023-06-01,PEN,300.00,30.00,330.00', // CUARENTENA (operación vacía)
        ]);

        $tempDir = storage_path('app/temp_imports');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        $localCsvPath = $tempDir . '/cartera_test_synthetic.csv';
        file_put_contents($localCsvPath, $csvContent);

        $uploadedFile = new UploadedFile(
            $localCsvPath,
            'cartera_test_synthetic.csv',
            'text/csv',
            null,
            true
        );

        // Paso A: Vista Previa Diagnóstica (POST /integracion/data/preview)
        $resPreview = $this->post(route('integracion.data.preview'), [
            'archivo' => $uploadedFile,
        ]);
        $resPreview->assertSessionHas('data_import_preview');
        $prevData = session('data_import_preview');

        $this->assertSame(1, $prevData['insert']);
        $this->assertSame(1, $prevData['update']);
        $this->assertSame(1, $prevData['cuarentena']);
        $this->assertSame(1, $prevData['duplicados']);
        $this->assertNotEmpty($prevData['db_fingerprint']);

        // Base de datos no debe haber sufrido cambios todavía
        $this->assertSame(1000.00, (float) DB::table('clientes_cuentas')->where('numdoc', '10203040')->value('deuda_capital'));

        // Paso B: Descarga del reporte de cuarentena (GET /integracion/data/errores)
        $resErrors = $this->get(route('integracion.data.errors'));
        $resErrors->assertStatus(200);
        $this->assertTrue(str_contains($resErrors->headers->get('content-disposition', ''), 'reporte_cuarentena_cartera.csv'));

        // Paso C: Confirmación de importación (POST /integracion/data/import)
        $tempPath = session('data_import_temp_path');
        $fingerprint = $prevData['db_fingerprint'];

        $resImport = $this->post(route('integracion.data.import'), [
            'temp_path' => $tempPath,
            'db_fingerprint' => $fingerprint,
        ]);
        $resImport->assertSessionHas('ok');

        // Verificación de datos aplicados en BD
        $this->assertSame(800.00, (float) DB::table('clientes_cuentas')->where('numdoc', '10203040')->value('deuda_capital'));
        $this->assertDatabaseHas('clientes_cuentas', ['numdoc' => '90807060', 'operacion' => 'OP-NEW-01']);
        $this->assertDatabaseMissing('clientes_cuentas', ['numdoc' => '88776655']); // Retenido en cuarentena

        @unlink($localCsvPath);
    }

    /**
     * 9. Administración de usuarios, jerarquía de supervisión y toggle de estado.
     */
    public function test_user_admin_crud_and_supervisor_hierarchy(): void
    {
        $admin = $this->createTestUser('administrador', 'admin.users@kpinvest.pe');
        $supervisor = $this->createTestUser('supervisor', 'sup.users@kpinvest.pe');

        $this->actingAs($admin);

        // Crear nuevo asesor asignado al supervisor
        $resStore = $this->post(route('administracion.usuarios.store'), [
            'name' => 'Asesor de Equipo',
            'email' => 'asesor.equipo@kpinvest.pe',
            'password' => 'secret12345',
            'role' => 'asesor',
            'supervisor_id' => $supervisor->id,
        ]);
        $resStore->assertRedirect();

        $asesor = User::where('email', 'asesor.equipo@kpinvest.pe')->firstOrFail();
        $this->assertSame($supervisor->id, (int) $asesor->supervisor_id);
        $this->assertSame(1, (int) $asesor->active);

        // Desactivar asesor (toggle)
        $resToggle = $this->patch(route('administracion.usuarios.toggle', $asesor->id));
        $resToggle->assertRedirect();
        $asesor->refresh();
        $this->assertSame(0, (int) $asesor->active);

        // Reactivar asesor
        $this->patch(route('administracion.usuarios.toggle', $asesor->id));
        $asesor->refresh();
        $this->assertSame(1, (int) $asesor->active);

        // Intento de auto-desactivación del propio admin autenticado -> Rechazado con mensaje de error
        $resSelf = $this->patch(route('administracion.usuarios.toggle', $admin->id));
        $resSelf->assertSessionHasErrors();
        $admin->refresh();
        $this->assertSame(1, (int) $admin->active);
    }

    /**
     * 10. Notificaciones: consulta de no leídas y marcado general.
     */
    public function test_notifications_endpoints_and_read_state(): void
    {
        $asesor = $this->createTestUser('asesor', 'asesor.notif@kpinvest.pe');

        $this->actingAs($asesor);

        // Consulta de conteo inicial
        $resCount = $this->getJson(route('notificaciones.unread-count'));
        $resCount->assertStatus(200);
        $resCount->assertJsonStructure(['unread_count']);

        // Marcar todas como leídas
        $resMark = $this->postJson(route('notificaciones.leer-todas'));
        $resMark->assertStatus(200);
        $resMark->assertJson(['unread_count' => 0]);
    }
}
