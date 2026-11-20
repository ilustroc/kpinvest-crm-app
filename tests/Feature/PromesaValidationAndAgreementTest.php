<?php

namespace Tests\Feature;

use App\Models\PromesaCuota;
use App\Models\PromesaOperacion;
use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Promesa\PromesaDocumentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PromesaValidationAndAgreementTest extends TestCase
{
    use DatabaseTransactions;

    private array $filesCreated = [];

    protected function tearDown(): void
    {
        PromesaDocumentService::$fakePdfConverter = null;

        foreach ($this->filesCreated as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function makeUser(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'active' => true,
        ], $extra));
    }

    private function createClientAccount(array $extra = []): array
    {
        $dni = '10'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $operation = (string) random_int(900000, 999999);

        DB::table('clientes_cuentas')->insert(array_merge([
            'numdoc' => $dni,
            'nombre' => 'ARCE CAUTI ROSA NATIVIDAD',
            'direccion' => 'CALLE SAN MARTIN 456',
            'entidad' => 'COMPARTAMOS FINANCIERA',
            'operacion' => $operation,
            'deuda_total' => 1500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));

        return [$dni, $operation];
    }

    /**
     * 1. Validación estricta de teléfonos: ^9[0-9]{8}$
     */
    public function test_phone_validation_accepts_valid_and_rejects_invalid(): void
    {
        Mail::fake();
        $admin = $this->makeUser('administrador');
        [$dni, $operation] = $this->createClientAccount();

        $validPhones = [
            '934043966',
            '906656019',
            '923925966',
        ];

        foreach ($validPhones as $phone) {
            $response = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
                'tipo' => 'cancelacion',
                'telefono' => $phone,
                'operaciones' => [$operation],
                'fecha_pago' => Carbon::now('America/Lima')->addDays(1)->toDateString(),
                'monto_cancel' => '100.00',
            ]);

            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('promesas_pago', [
                'numdoc' => $dni,
                'telefono' => $phone,
            ]);
        }

        $invalidPhones = [
            '190273',
            '1231091',
            '51923925966',
            '+51 923 925 966',
            '+51923925966',
            '92392596',     // 8 dígitos
            '9239259660',   // 10 dígitos
            '823925966',    // no empieza con 9
            '923 925 966',  // con espacios
            '923-925-966',  // con guiones
            '92392596A',    // con letras
        ];

        foreach ($invalidPhones as $badPhone) {
            $response = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
                'tipo' => 'cancelacion',
                'telefono' => $badPhone,
                'operaciones' => [$operation],
                'fecha_pago' => Carbon::now('America/Lima')->addDays(1)->toDateString(),
                'monto_cancel' => '100.00',
            ]);

            $response->assertSessionHasErrors(['telefono']);
        }
    }

    /**
     * 2. Cancelaciones con fechas pasadas, actuales y futuras en America/Lima
     */
    public function test_cancelaciones_reject_past_date_and_accept_current_and_future_dates(): void
    {
        Mail::fake();
        $admin = $this->makeUser('administrador');
        [$dni, $operation] = $this->createClientAccount();

        $todayLima = Carbon::now('America/Lima')->toDateString();
        $yesterdayLima = Carbon::now('America/Lima')->subDay()->toDateString();
        $tomorrowLima = Carbon::now('America/Lima')->addDay()->toDateString();

        // 2.1 Fecha pasada -> Rechazada
        $resPast = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
            'tipo' => 'cancelacion',
            'telefono' => '934043966',
            'operaciones' => [$operation],
            'fecha_pago' => $yesterdayLima,
            'monto_cancel' => '150.00',
        ]);
        $resPast->assertSessionHasErrors(['fecha_pago']);

        // 2.2 Fecha actual -> Aceptada
        $resCurrent = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
            'tipo' => 'cancelacion',
            'telefono' => '934043966',
            'operaciones' => [$operation],
            'fecha_pago' => $todayLima,
            'monto_cancel' => '150.00',
        ]);
        $resCurrent->assertSessionHasNoErrors();

        // 2.3 Fecha futura -> Aceptada y verifica que fecha_promesa sea la fecha de registro y fecha_pago la pactada
        $resFuture = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
            'tipo' => 'cancelacion',
            'telefono' => '934043966',
            'operaciones' => [$operation],
            'fecha_pago' => $tomorrowLima,
            'monto_cancel' => '200.00',
            'nota' => 'Cancelación fecha futura test',
        ]);
        $resFuture->assertSessionHasNoErrors();

        $promesa = PromesaPago::where('numdoc', $dni)->where('nota', 'Cancelación fecha futura test')->firstOrFail();
        $this->assertSame($todayLima, $promesa->fecha_promesa->toDateString(), 'fecha_promesa debe ser la fecha actual de registro');
        $this->assertSame($tomorrowLima, $promesa->fecha_pago->toDateString(), 'fecha_pago debe ser la fecha pactada');
    }

    /**
     * 3. Convenios con una o varias cuotas: validación de fechas pasadas en cualquier cuota
     */
    public function test_convenios_reject_past_quota_dates_and_accept_current_or_future(): void
    {
        Mail::fake();
        $admin = $this->makeUser('administrador');
        [$dni, $operation] = $this->createClientAccount();

        $todayLima = Carbon::now('America/Lima')->toDateString();
        $yesterdayLima = Carbon::now('America/Lima')->subDay()->toDateString();
        $future1 = Carbon::now('America/Lima')->addDays(15)->toDateString();
        $future2 = Carbon::now('America/Lima')->addDays(45)->toDateString();

        // Convenio con primera cuota en el pasado -> Rechazado
        $resPast1 = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
            'tipo' => 'convenio',
            'telefono' => '934043966',
            'operaciones' => [$operation],
            'nro_cuotas' => 2,
            'monto_convenio' => '300.00',
            'cron_fecha' => [$yesterdayLima, $future1],
            'cron_monto' => ['150.00', '150.00'],
        ]);
        $resPast1->assertSessionHasErrors(['cron_fecha.0']);

        // Convenio con segunda cuota en el pasado -> Rechazado
        $resPast2 = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
            'tipo' => 'convenio',
            'telefono' => '934043966',
            'operaciones' => [$operation],
            'nro_cuotas' => 2,
            'monto_convenio' => '300.00',
            'cron_fecha' => [$todayLima, $yesterdayLima],
            'cron_monto' => ['150.00', '150.00'],
        ]);
        $resPast2->assertSessionHasErrors(['cron_fecha.1']);

        // Convenio válido con cuotas a partir de hoy -> Aprobado
        $resValid = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
            'tipo' => 'convenio',
            'telefono' => '934043966',
            'operaciones' => [$operation],
            'nro_cuotas' => 2,
            'monto_convenio' => '300.00',
            'cron_fecha' => [$todayLima, $future2],
            'cron_monto' => ['150.00', '150.00'],
            'nota' => 'Convenio válido 2 cuotas',
        ]);
        $resValid->assertSessionHasNoErrors();

        $promesa = PromesaPago::where('numdoc', $dni)->where('nota', 'Convenio válido 2 cuotas')->firstOrFail();
        $this->assertSame($todayLima, $promesa->fecha_promesa->toDateString());
        $this->assertSame($todayLima, $promesa->fecha_pago->toDateString());
        $this->assertSame(2, DB::table('promesa_cuotas')->where('promesa_id', $promesa->id)->count());
    }

    /**
     * 4. Convenios con cuota balón: validación de fechas pasadas en todas las cuotas incluida la balón
     */
    public function test_convenio_balon_rejects_past_dates_in_regular_and_balloon_quotas(): void
    {
        Mail::fake();
        $admin = $this->makeUser('administrador');
        [$dni, $operation] = $this->createClientAccount();

        $todayLima = Carbon::now('America/Lima')->toDateString();
        $yesterdayLima = Carbon::now('America/Lima')->subDay()->toDateString();
        $future1 = Carbon::now('America/Lima')->addDays(30)->toDateString();
        $future2 = Carbon::now('America/Lima')->addDays(60)->toDateString();

        // Cuota balón con fecha en el pasado -> Rechazada
        $resBalonPast = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
            'tipo' => 'convenio_balon',
            'telefono' => '906656019',
            'operaciones' => [$operation],
            'nro_cuotas' => 2,
            'monto_convenio' => '500.00',
            'cron_fecha' => [$todayLima, $yesterdayLima],
            'cron_monto' => ['100.00', '400.00'],
            'cron_balon' => 2,
        ]);
        $resBalonPast->assertSessionHasErrors(['cron_fecha.1']);

        // Cuota balón válida con fechas futuras -> Aceptada
        $resBalonValid = $this->actingAs($admin)->post(route('clientes.promesas.store', $dni), [
            'tipo' => 'convenio_balon',
            'telefono' => '906656019',
            'operaciones' => [$operation],
            'nro_cuotas' => 3,
            'monto_convenio' => '800.00',
            'cron_fecha' => [$todayLima, $future1, $future2],
            'cron_monto' => ['100.00', '100.00', '600.00'],
            'cron_balon' => 3,
            'nota' => 'Balon valid 3 cuotas',
        ]);
        $resBalonValid->assertSessionHasNoErrors();

        $balon = PromesaPago::where('numdoc', $dni)->where('nota', 'Balon valid 3 cuotas')->firstOrFail();
        $this->assertDatabaseHas('promesa_cuotas', [
            'promesa_id' => $balon->id,
            'nro' => 3,
            'es_balon' => 1,
            'fecha' => $future2,
        ]);
    }

    /**
     * 5. Validaciones del backend sin JavaScript (solicitudes HTTP directas)
     */
    public function test_backend_validations_reject_direct_invalid_http_requests(): void
    {
        Mail::fake();
        $admin = $this->makeUser('administrador');
        [$dni, $operation] = $this->createClientAccount();

        // Petición directa con teléfono omitido
        $res1 = $this->actingAs($admin)->postJson(route('clientes.promesas.store', $dni), [
            'tipo' => 'cancelacion',
            'operaciones' => [$operation],
            'fecha_pago' => Carbon::now('America/Lima')->addDay()->toDateString(),
            'monto_cancel' => '100.00',
        ]);
        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['telefono']);

        // Petición directa con teléfono con prefijo internacional
        $res2 = $this->actingAs($admin)->postJson(route('clientes.promesas.store', $dni), [
            'tipo' => 'cancelacion',
            'telefono' => '+51934043966',
            'operaciones' => [$operation],
            'fecha_pago' => Carbon::now('America/Lima')->addDay()->toDateString(),
            'monto_cancel' => '100.00',
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['telefono']);

        // Petición directa con fecha pasada
        $res3 = $this->actingAs($admin)->postJson(route('clientes.promesas.store', $dni), [
            'tipo' => 'cancelacion',
            'telefono' => '934043966',
            'operaciones' => [$operation],
            'fecha_pago' => Carbon::now('America/Lima')->subDays(5)->toDateString(),
            'monto_cancel' => '100.00',
        ]);
        $res3->assertStatus(422);
        $res3->assertJsonValidationErrors(['fecha_pago']);

        // Petición directa con operaciones de otro cliente
        $res4 = $this->actingAs($admin)->postJson(route('clientes.promesas.store', $dni), [
            'tipo' => 'cancelacion',
            'telefono' => '934043966',
            'operaciones' => ['999999999_INEXISTENTE'],
            'fecha_pago' => Carbon::now('America/Lima')->addDay()->toDateString(),
            'monto_cancel' => '100.00',
        ]);
        $res4->assertStatus(422);
        $res4->assertJsonValidationErrors(['operaciones']);
    }

    /**
     * 6 y 8. Generación y descarga de acuerdos aprobados con PDF simulado (sin consumir créditos iLovePDF)
     */
    public function test_agreement_generation_and_download_for_approved_promesas_with_simulated_pdf(): void
    {
        $admin = $this->makeUser('administrador');
        [$dni, $operation] = $this->createClientAccount();

        // Configurar simulador de iLovePDF para pruebas
        PromesaDocumentService::$fakePdfConverter = function (string $docxOut, string $isolatedTmpDir, PromesaPago $promesa) {
            $pdfPath = $isolatedTmpDir."/Acuerdo_{$promesa->id}_{$promesa->numdoc}.pdf";
            file_put_contents($pdfPath, "%PDF-1.4 Simulated PDF for Promesa {$promesa->id}\n%%EOF");

            return $pdfPath;
        };

        // Crear propuesta aprobada de tipo cancelación (simulando caso ID 4575)
        $promesa = PromesaPago::create([
            'numdoc' => $dni,
            'dni' => $dni,
            'tipo' => 'cancelacion',
            'telefono' => '934043966',
            'fecha_promesa' => Carbon::now('America/Lima')->toDateString(),
            'fecha_pago' => Carbon::now('America/Lima')->addDay()->toDateString(),
            'monto' => 100.00,
            'workflow_estado' => 'aprobada',
            'user_id' => $admin->id,
            'operacion' => $operation,
            'aprobado_por' => $admin->id,
            'aprobado_at' => now(),
        ]);

        PromesaOperacion::create([
            'promesa_id' => $promesa->id,
            'operacion' => $operation,
        ]);

        // Intento de descarga de acuerdo aprobado
        $response = $this->actingAs($admin)->get(route('promesas.acuerdo', $promesa->id));
        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("Acuerdo_{$promesa->id}_{$dni}.pdf", $response->headers->get('Content-Disposition'));

        // Verificar que el PDF generado se guardó en el almacenamiento privado
        $savedPdf = storage_path("app/promesas/pdfs/Acuerdo_{$promesa->id}_{$dni}.pdf");
        $this->assertFileExists($savedPdf);
        $this->filesCreated[] = $savedPdf;
        $this->filesCreated[] = storage_path("app/promesas/docx/Acuerdo_{$promesa->id}_{$dni}.docx");

        // 8. Reutilización de PDF existente: Segunda llamada debe servir el archivo existente sin invocar convertidor
        $calledConverter = false;
        PromesaDocumentService::$fakePdfConverter = function () use (&$calledConverter) {
            $calledConverter = true;
            throw new \RuntimeException('No debería invocarse el convertidor si el PDF ya existe.');
        };

        $cachedResponse = $this->actingAs($admin)->get(route('promesas.acuerdo', $promesa->id));
        $cachedResponse->assertStatus(200);
        $this->assertFalse($calledConverter, 'Debe reutilizar el PDF existente sin volver a procesar con iLovePDF.');

        // 6.2 Generación para propuesta aprobada de tipo convenio
        PromesaDocumentService::$fakePdfConverter = function (string $docxOut, string $isolatedTmpDir, PromesaPago $p) {
            $pdfPath = $isolatedTmpDir."/Acuerdo_{$p->id}_{$p->numdoc}.pdf";
            file_put_contents($pdfPath, "%PDF-1.4 Simulated PDF for Convenio {$p->id}\n%%EOF");

            return $pdfPath;
        };

        $promesaConvenio = PromesaPago::create([
            'numdoc' => $dni,
            'dni' => $dni,
            'tipo' => 'convenio',
            'telefono' => '906656019',
            'fecha_promesa' => Carbon::now('America/Lima')->toDateString(),
            'fecha_pago' => Carbon::now('America/Lima')->toDateString(),
            'nro_cuotas' => 2,
            'monto_convenio' => 400.00,
            'monto_cuota' => 200.00,
            'workflow_estado' => 'aprobada',
            'user_id' => $admin->id,
            'operacion' => $operation,
            'aprobado_por' => $admin->id,
            'aprobado_at' => now(),
        ]);
        PromesaOperacion::create(['promesa_id' => $promesaConvenio->id, 'operacion' => $operation]);
        PromesaCuota::create(['promesa_id' => $promesaConvenio->id, 'nro' => 1, 'fecha' => now()->toDateString(), 'monto' => 200.00, 'es_balon' => 0]);
        PromesaCuota::create(['promesa_id' => $promesaConvenio->id, 'nro' => 2, 'fecha' => now()->addDays(30)->toDateString(), 'monto' => 200.00, 'es_balon' => 0]);

        $resConvenio = $this->actingAs($admin)->get(route('promesas.acuerdo', $promesaConvenio->id));
        $resConvenio->assertStatus(200);
        $this->assertSame('application/pdf', $resConvenio->headers->get('Content-Type'));
        $this->filesCreated[] = storage_path("app/promesas/pdfs/Acuerdo_{$promesaConvenio->id}_{$dni}.pdf");
        $this->filesCreated[] = storage_path("app/promesas/docx/Acuerdo_{$promesaConvenio->id}_{$dni}.docx");

        // 6.3 Generación para propuesta aprobada con cuota balón
        $promesaBalon = PromesaPago::create([
            'numdoc' => $dni,
            'dni' => $dni,
            'tipo' => 'convenio',
            'telefono' => '923925966',
            'fecha_promesa' => Carbon::now('America/Lima')->toDateString(),
            'fecha_pago' => Carbon::now('America/Lima')->toDateString(),
            'nro_cuotas' => 2,
            'monto_convenio' => 500.00,
            'monto_cuota' => 250.00,
            'workflow_estado' => 'aprobada',
            'user_id' => $admin->id,
            'operacion' => $operation,
            'aprobado_por' => $admin->id,
            'aprobado_at' => now(),
        ]);
        PromesaOperacion::create(['promesa_id' => $promesaBalon->id, 'operacion' => $operation]);
        PromesaCuota::create(['promesa_id' => $promesaBalon->id, 'nro' => 1, 'fecha' => now()->toDateString(), 'monto' => 100.00, 'es_balon' => 0]);
        PromesaCuota::create(['promesa_id' => $promesaBalon->id, 'nro' => 2, 'fecha' => now()->addDays(30)->toDateString(), 'monto' => 400.00, 'es_balon' => 1]);

        $resBalon = $this->actingAs($admin)->get(route('promesas.acuerdo', $promesaBalon->id));
        $resBalon->assertStatus(200);
        $this->assertSame('application/pdf', $resBalon->headers->get('Content-Type'));
        $this->filesCreated[] = storage_path("app/promesas/pdfs/Acuerdo_{$promesaBalon->id}_{$dni}.pdf");
        $this->filesCreated[] = storage_path("app/promesas/docx/Acuerdo_{$promesaBalon->id}_{$dni}.docx");

        // Propuesta no aprobada debe ser rechazada con 403
        $promesaPendiente = PromesaPago::create([
            'numdoc' => $dni,
            'dni' => $dni,
            'tipo' => 'cancelacion',
            'telefono' => '934043966',
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDay()->toDateString(),
            'monto' => 100.00,
            'workflow_estado' => 'pendiente',
            'user_id' => $admin->id,
        ]);
        $resForbidden = $this->actingAs($admin)->get(route('promesas.acuerdo', $promesaPendiente->id));
        $resForbidden->assertStatus(403);
    }

    /**
     * 7. Manejo seguro de plantilla faltante (sin exponer rutas internas ni error 500)
     */
    public function test_missing_template_returns_clean_404_without_leaking_internal_paths(): void
    {
        $admin = $this->makeUser('administrador');
        [$dni, $operation] = $this->createClientAccount();

        // Mover temporalmente las plantillas existentes para simular ausencia total
        $templateStorage = storage_path('app/templates/Acuerdo_de_Pago_DNI_{dni}.docx');
        $templateResource = resource_path('templates/Acuerdo_de_Pago_DNI_{dni}.docx');

        $backupStorage = $templateStorage.'.bak_test';
        $backupResource = $templateResource.'.bak_test';

        if (is_file($templateStorage)) {
            @rename($templateStorage, $backupStorage);
        }
        if (is_file($templateResource)) {
            @rename($templateResource, $backupResource);
        }

        try {
            $promesa = PromesaPago::create([
                'numdoc' => $dni,
                'dni' => $dni,
                'tipo' => 'cancelacion',
                'telefono' => '934043966',
                'fecha_promesa' => now()->toDateString(),
                'fecha_pago' => now()->addDay()->toDateString(),
                'monto' => 100.00,
                'workflow_estado' => 'aprobada',
                'user_id' => $admin->id,
                'operacion' => $operation,
            ]);

            $response = $this->actingAs($admin)->get(route('promesas.acuerdo', $promesa->id));

            // Debe devolver 404 limpio en vez de 500
            $this->assertSame(404, $response->getStatusCode());

            // No debe filtrar rutas del servidor como /home/u480021566/... o storage/app/...
            $content = (string) $response->getContent();
            $this->assertStringNotContainsString('/home/u480021566', $content);
            $this->assertStringNotContainsString('C:\\', $content);
        } finally {
            if (is_file($backupStorage)) {
                @rename($backupStorage, $templateStorage);
            }
            if (is_file($backupResource)) {
                @rename($backupResource, $templateResource);
            }
        }
    }
}
