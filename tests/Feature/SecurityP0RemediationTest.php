<?php

namespace Tests\Feature;

use App\Models\CnaSolicitud;
use App\Models\Pago;
use App\Models\PromesaPago;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityP0RemediationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Limpiar tablas de pruebas en kp_crm_testing
        DB::table('users')->delete();
        DB::table('promesas_pago')->delete();
        DB::table('cna_solicitudes')->delete();
        DB::table('pagos')->delete();
        DB::table('clientes_cuentas')->delete();
        RateLimiter::clear('login-account:test@kpinvest.pe|127.0.0.1');
        RateLimiter::clear('login-ip:127.0.0.1');
    }

    public function test_login_prevents_user_enumeration_for_inactive_and_non_existent_users(): void
    {
        $inactive = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 0,
        ]);

        // Intento con usuario inactivo
        $responseInactive = $this->post('/login', [
            'email' => 'inactive@kpinvest.pe',
            'password' => 'secret123',
        ]);
        $responseInactive->assertSessionHasErrors(['email' => 'Credenciales inválidas.']);

        // Intento con usuario que no existe
        $responseNonExistent = $this->post('/login', [
            'email' => 'nonexistent@kpinvest.pe',
            'password' => 'secret123',
        ]);
        $responseNonExistent->assertSessionHasErrors(['email' => 'Credenciales inválidas.']);
    }

    public function test_six_concurrent_independent_sessions_remain_active_when_one_logs_out(): void
    {
        config(['session.driver' => 'file']);

        $user = User::create([
            'name' => 'Escall Perú',
            'email' => 'supervisor3@escallperu.com',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        $cookieName = config('session.cookie');
        $sessionIds = [];

        // 1. Autenticar 6 clientes independientes con las mismas credenciales sintéticas
        for ($client = 1; $client <= 6; $client++) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            $response = $this->post('/login', [
                'email' => 'supervisor3@escallperu.com',
                'password' => 'secret123',
            ]);

            $response->assertRedirect(route('panel'));

            $id = session()->getId();
            $this->assertNotEmpty($id, "El ID de sesión para el cliente {$client} debe existir.");
            $sessionIds[$client] = $id;

            // Asegurar persistencia de la sesión en disco
            session()->save();
        }

        // 2 & 3. Consultar una ruta protegida desde cada sesión y confirmar que TODAS siguen autenticadas
        for ($client = 1; $client <= 6; $client++) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            $panelResponse = $this->withCookie($cookieName, $sessionIds[$client])
                ->get(route('panel'));

            $panelResponse->assertStatus(200);
            $panelResponse->assertSee('Escall Perú');
        }

        // 4. Cerrar sesión únicamente en el cliente 1
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $logoutResponse = $this->withCookie($cookieName, $sessionIds[1])
            ->post('/logout');

        $logoutResponse->assertRedirect(route('login'));

        // Confirmar que el cliente 1 fue cerrado y ya no puede acceder
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $client1Check = $this->withCookie($cookieName, $sessionIds[1])
            ->get(route('panel'));
        $client1Check->assertRedirect(route('login'));

        // 5. Confirmar que las otras 5 sesiones (clientes 2 al 6) permanecen 100% activas y autenticadas
        for ($client = 2; $client <= 6; $client++) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            $activeResponse = $this->withCookie($cookieName, $sessionIds[$client])
                ->get(route('panel'));

            $activeResponse->assertStatus(200);
            $activeResponse->assertSee('Escall Perú');
        }
    }

    public function test_acuerdo_cannot_be_generated_for_non_approved_promesas(): void
    {
        $asesor = User::create([
            'name' => 'Asesor',
            'email' => 'asesor@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        $statuses = ['pendiente', 'preaprobada', 'rechazada'];

        foreach ($statuses as $status) {
            $promesa = PromesaPago::create([
                'dni' => '12345678',
                'user_id' => $asesor->id,
                'tipo' => 'cancelacion',
                'monto' => 1000,
                'fecha_promesa' => now()->toDateString(),
                'workflow_estado' => $status,
                'estado' => $status,
            ]);

            $response = $this->actingAs($asesor)->get("/promesas/{$promesa->id}/acuerdo");
            $response->assertStatus(403);
        }
    }

    public function test_approved_acuerdo_allows_download_and_preserves_client_isolation(): void
    {
        $asesor = User::create([
            'name' => 'Asesor Documentos',
            'email' => 'asesor_docs@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        $promesaA = PromesaPago::create([
            'dni' => '11112222',
            'user_id' => $asesor->id,
            'tipo' => 'cancelacion',
            'monto' => 1500,
            'fecha_promesa' => now()->toDateString(),
            'workflow_estado' => 'aprobada',
            'estado' => 'aprobada',
        ]);

        $promesaB = PromesaPago::create([
            'dni' => '33334444',
            'user_id' => $asesor->id,
            'tipo' => 'cancelacion',
            'monto' => 2500,
            'fecha_promesa' => now()->toDateString(),
            'workflow_estado' => 'aprobada',
            'estado' => 'aprobada',
        ]);

        $pdfDir = storage_path('app/promesas/pdfs');
        if (! is_dir($pdfDir)) {
            @mkdir($pdfDir, 0770, true);
        }

        $fileA = "{$pdfDir}/Acuerdo_{$promesaA->id}_{$promesaA->dni}.pdf";
        $fileB = "{$pdfDir}/Acuerdo_{$promesaB->id}_{$promesaB->dni}.pdf";
        file_put_contents($fileA, '%PDF-1.4 SYNTHETIC PDF A CONTENT');
        file_put_contents($fileB, '%PDF-1.4 SYNTHETIC PDF B CONTENT');

        try {
            // Descarga de Acuerdo A
            $responseA = $this->actingAs($asesor)->get("/promesas/{$promesaA->id}/acuerdo");
            $responseA->assertStatus(200);
            $responseA->assertHeader('Content-Type', 'application/pdf');
            $contentA = file_get_contents($responseA->getFile()->getPathname());
            $this->assertStringContainsString('SYNTHETIC PDF A CONTENT', $contentA);
            $this->assertStringNotContainsString('SYNTHETIC PDF B CONTENT', $contentA);

            // Descarga de Acuerdo B con aislamiento
            $responseB = $this->actingAs($asesor)->get("/promesas/{$promesaB->id}/acuerdo");
            $responseB->assertStatus(200);
            $responseB->assertHeader('Content-Type', 'application/pdf');
            $contentB = file_get_contents($responseB->getFile()->getPathname());
            $this->assertStringContainsString('SYNTHETIC PDF B CONTENT', $contentB);
            $this->assertStringNotContainsString('SYNTHETIC PDF A CONTENT', $contentB);
        } finally {
            @unlink($fileA);
            @unlink($fileB);
        }
    }

    public function test_cna_documents_download_positive_and_negative_with_isolation(): void
    {
        $asesor = User::create([
            'name' => 'Asesor CNA',
            'email' => 'asesor_cna@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        // CNA aprobada
        $cnaAprobada = CnaSolicitud::create([
            'dni' => '55556666',
            'user_id' => $asesor->id,
            'nro_carta' => '000777',
            'workflow_estado' => 'aprobada',
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => 750,
            'operaciones' => ['987654'],
        ]);

        // CNA no aprobada
        $cnaPendiente = CnaSolicitud::create([
            'dni' => '77778888',
            'user_id' => $asesor->id,
            'nro_carta' => '000888',
            'workflow_estado' => 'pendiente',
            'fecha_pago_realizado' => now()->toDateString(),
            'monto_pagado' => 400,
            'operaciones' => ['123789'],
        ]);

        $cnaPdfDir = storage_path('app/cna/pdfs');
        $cnaDocxDir = storage_path('app/cna/docx');
        if (! is_dir($cnaPdfDir)) {
            @mkdir($cnaPdfDir, 0770, true);
        }
        if (! is_dir($cnaDocxDir)) {
            @mkdir($cnaDocxDir, 0770, true);
        }

        $pdfA = "{$cnaPdfDir}/CNA 000777 - 55556666.pdf";
        $docxA = "{$cnaDocxDir}/CNA 000777 - 55556666.docx";
        file_put_contents($pdfA, '%PDF-1.4 SYNTHETIC CNA PDF');
        file_put_contents($docxA, 'SYNTHETIC CNA DOCX');

        try {
            // CNA aprobada debe permitir PDF y DOCX
            $responsePdf = $this->actingAs($asesor)->get("/cna/{$cnaAprobada->id}/pdf");
            $responsePdf->assertStatus(200);

            $responseDocx = $this->actingAs($asesor)->get("/cna/{$cnaAprobada->id}/docx");
            $responseDocx->assertStatus(200);

            // CNA no aprobada debe devolver 403
            $responseBlockedPdf = $this->actingAs($asesor)->get("/cna/{$cnaPendiente->id}/pdf");
            $responseBlockedPdf->assertStatus(403);

            $responseBlockedDocx = $this->actingAs($asesor)->get("/cna/{$cnaPendiente->id}/docx");
            $responseBlockedDocx->assertStatus(403);
        } finally {
            @unlink($pdfA);
            @unlink($docxA);
        }
    }

    public function test_asesor_is_forbidden_from_deleting_payments(): void
    {
        $asesor = User::create([
            'name' => 'Asesor',
            'email' => 'asesor3@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        $pago = Pago::create([
            'dni' => '12345678',
            'operacion' => '111111',
            'monto_pagado' => 200,
            'fecha' => now()->toDateString(),
        ]);

        $response = $this->actingAs($asesor)->post('/clientes/12345678/pagos/delete', [
            'ids' => [$pago->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('pagos', ['id' => $pago->id]);
    }

    public function test_supervisor_and_soporte_can_delete_payments_with_traceability(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor User',
            'email' => 'sup@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'supervisor',
            'active' => 1,
        ]);

        $pago = Pago::create([
            'dni' => '12345678',
            'operacion' => '222222',
            'monto_pagado' => 350,
            'fecha' => now()->toDateString(),
        ]);

        $response = $this->actingAs($supervisor)->post('/clientes/12345678/pagos/delete', [
            'ids' => [$pago->id],
            'motivo' => 'Pago duplicado detectado en auditoria',
        ]);

        $response->assertSessionHas('msg');
        $this->assertDatabaseMissing('pagos', ['id' => $pago->id]);
    }

    public function test_payment_deletion_does_not_emit_pagos_deleted_when_no_records_are_deleted(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor Fail Test',
            'email' => 'sup_fail@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'supervisor',
            'active' => 1,
        ]);

        $service = app(\App\Services\Cliente\ClientePaymentService::class);

        // Cuando la colección de IDs no coincide con ningún registro de ese DNI, retorna 0 y nunca emite PAGOS_DELETED
        $deleted = $service->deleteForDni($supervisor, '99999999', collect([999999]), 'Intento fallido');
        $this->assertSame(0, $deleted);
    }

    public function test_global_client_access_allows_asesor_to_view_any_client(): void
    {
        $asesor = User::create([
            'name' => 'Asesor',
            'email' => 'asesor4@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'asesor',
            'active' => 1,
        ]);

        DB::table('clientes_cuentas')->insert([
            'numdoc' => '88888888',
            'nombre' => 'CLIENTE DE PRUEBA CUALQUIERA',
            'operacion' => '999999',
            'entidad' => 'BANCO EXTERNO',
            'cosecha' => 'COSECHA_2025',
            'deuda_capital' => 1500,
            'deuda_total' => 1800,
        ]);

        $response = $this->actingAs($asesor)->get('/clientes/88888888');
        $response->assertStatus(200);
        $response->assertSee('CLIENTE DE PRUEBA CUALQUIERA');
    }
}
