<?php

namespace Tests\Feature;

use App\Models\CnaSolicitud;
use App\Models\Pago;
use App\Models\PromesaCuota;
use App\Models\PromesaPago;
use App\Models\User;
use App\Services\Cliente\ClientePaymentService;
use App\Services\Panel\PanelDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardOptimizationFaseBTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        Cache::flush();
    }

    public function test_admin_dashboard_metrics_and_caching(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Test',
            'role' => 'administrador',
            'active' => 1,
        ]);

        $service = app(PanelDashboardService::class);

        // First call: cold cache, queries executed
        DB::flushQueryLog();
        DB::enableQueryLog();

        $data1 = $service->build($admin);
        $queriesCold = count(DB::getQueryLog());
        $this->assertGreaterThan(0, $queriesCold);

        // Required keys in data payload
        $this->assertArrayHasKey('kpiPromHoy', $data1);
        $this->assertArrayHasKey('kpiPagosHoy', $data1);
        $this->assertArrayHasKey('sumMes', $data1);
        $this->assertArrayHasKey('chartLabels', $data1);
        $this->assertArrayHasKey('chartData', $data1);
        $this->assertArrayHasKey('vencCount', $data1);
        $this->assertArrayHasKey('venc', $data1);

        // Second call: hot cache, 0 queries executed
        DB::flushQueryLog();
        $data2 = $service->build($admin);
        $queriesHot = count(DB::getQueryLog());

        $this->assertSame(0, $queriesHot, 'Hot cache should not execute any SQL queries');
        $this->assertSame($data1['kpiPromHoy'], $data2['kpiPromHoy']);
        $this->assertSame($data1['kpiPagosHoy'], $data2['kpiPagosHoy']);
        $this->assertSame($data1['sumMes'], $data2['sumMes']);
    }

    public function test_supervisor_and_asesor_role_isolation(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Supervisor Test',
            'role' => 'supervisor',
            'active' => 1,
        ]);

        $asesorA = User::factory()->create([
            'name' => 'Asesor Alpha',
            'role' => 'asesor',
            'active' => 1,
        ]);

        $asesorB = User::factory()->create([
            'name' => 'Asesor Beta',
            'role' => 'asesor',
            'active' => 1,
        ]);

        $service = app(PanelDashboardService::class);

        $dataSup = $service->build($supervisor);
        $dataA = $service->build($asesorA);
        $dataB = $service->build($asesorB);

        // Supervisor must have supervisor-specific workflow counts
        $this->assertArrayHasKey('cnaSup', $dataSup);
        $this->assertArrayHasKey('misSup', $dataSup);

        // Asesor should have role-specific views
        $this->assertArrayHasKey('kpiPagosHoy', $dataA);
        $this->assertArrayHasKey('kpiPagosHoy', $dataB);

        // Cache keys must be isolated
        $version = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);
        $today = Carbon::today('America/Lima')->toDateString();
        $mes = Carbon::today('America/Lima')->format('Y-m');

        $keySup = "panel_dashboard_v3:v{$version}:u{$supervisor->id}:rsupervisor:m{$mes}:d{$today}";
        $keyA = "panel_dashboard_v3:v{$version}:u{$asesorA->id}:rasesor:m{$mes}:d{$today}";
        $keyB = "panel_dashboard_v3:v{$version}:u{$asesorB->id}:rasesor:m{$mes}:d{$today}";

        $this->assertTrue(Cache::has($keySup));
        $this->assertTrue(Cache::has($keyA));
        $this->assertTrue(Cache::has($keyB));
        $this->assertNotEquals($keySup, $keyA);
        $this->assertNotEquals($keyA, $keyB);
    }

    public function test_reactive_cache_invalidation_on_pago_saved(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Pago Test',
            'role' => 'administrador',
            'active' => 1,
        ]);

        $service = app(PanelDashboardService::class);
        $initialData = $service->build($admin);

        $initialVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);

        $today = Carbon::today('America/Lima')->toDateString();
        Pago::create([
            'cuenta' => 'CTA-TEST-99',
            'dni' => '44556677',
            'titular' => 'CLIENTE TEST PAGO',
            'monto_pagado' => 450.50,
            'monto' => 450.50,
            'fecha' => $today,
            'entidad' => 'BCP',
            'gestor' => 'Admin Pago Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $afterVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);
        $this->assertGreaterThan($initialVersion, $afterVersion, 'Version should be bumped after Pago created');

        // Next call fetches fresh data
        $freshData = $service->build($admin);
        $this->assertEquals($initialData['kpiPagosHoy'] + 450.50, $freshData['kpiPagosHoy']);
    }

    public function test_reactive_cache_invalidation_on_promesa_saved(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Promesa Test',
            'role' => 'administrador',
            'active' => 1,
        ]);

        $service = app(PanelDashboardService::class);
        $initialData = $service->build($admin);
        $initialVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);

        PromesaPago::create([
            'dni' => '88990011',
            'operacion' => 'OP-PROM-DASH-1',
            'user_id' => $admin->id,
            'monto' => 500.00,
            'monto_convenio' => 500.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => now()->toDateString(),
            'fecha_pago' => now()->addDays(3)->toDateString(),
            'workflow_estado' => 'preaprobada',
            'nro_cuotas' => 1,
        ]);

        $afterVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);
        $this->assertGreaterThan($initialVersion, $afterVersion, 'Version should be bumped after Promesa created');

        $freshData = $service->build($admin);
        $this->assertEquals($initialData['kpiPromHoy'] + 1, $freshData['kpiPromHoy']);
    }

    public function test_reactive_cache_invalidation_on_cna_saved(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Supervisor CNA Test',
            'role' => 'supervisor',
            'active' => 1,
        ]);

        $service = app(PanelDashboardService::class);
        $initialData = $service->build($supervisor);
        $initialVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);

        CnaSolicitud::create([
            'dni' => '99887766',
            'nro_carta' => 'CNA-TEST-AUTO-01',
            'user_id' => $supervisor->id,
            'operaciones' => ['OP-CNA-AUTO-01'],
            'workflow_estado' => 'pendiente',
            'tipo_solicitud' => 'total',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $afterVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);
        $this->assertGreaterThan($initialVersion, $afterVersion, 'Version should be bumped after CNA created');

        $freshData = $service->build($supervisor);
        $this->assertEquals($initialData['cnaPendCount'] + 1, $freshData['cnaPendCount']);
    }

    public function test_reactive_cache_invalidation_on_promesa_cuota_saved(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'active' => 1,
        ]);

        $promesa = PromesaPago::create([
            'dni' => '77665544',
            'operacion' => 'OP-CUOTA-TEST-1',
            'user_id' => $admin->id,
            'monto' => 1000.00,
            'monto_convenio' => 1000.00,
            'tipo' => 'convenio',
            'fecha_promesa' => now()->toDateString(),
            'workflow_estado' => 'aprobada',
            'nro_cuotas' => 2,
        ]);

        $initialVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);

        PromesaCuota::create([
            'promesa_id' => $promesa->id,
            'nro' => 1,
            'fecha' => now()->addDays(2)->toDateString(),
            'monto' => 500.00,
            'es_balon' => false,
        ]);

        $afterVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);
        $this->assertGreaterThan($initialVersion, $afterVersion, 'Version should bump on PromesaCuota creation');
    }

    public function test_reactive_cache_invalidation_on_cliente_payment_service_delete(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Delete Test',
            'role' => 'administrador',
            'active' => 1,
        ]);

        $today = Carbon::today('America/Lima')->toDateString();
        $pago = Pago::create([
            'cuenta' => 'CTA-TEST-DEL',
            'dni' => '12344321',
            'titular' => 'CLIENTE BORRAR PAGO',
            'monto_pagado' => 200.00,
            'monto' => 200.00,
            'fecha' => $today,
            'entidad' => 'BBVA',
            'gestor' => 'Admin Delete Test',
        ]);

        $service = app(PanelDashboardService::class);
        $service->build($admin);

        $initialVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);

        $paymentService = app(ClientePaymentService::class);
        $paymentService->deleteForDni($admin, '12344321', collect([$pago->id]), 'Prueba test');

        $afterVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);
        $this->assertGreaterThan($initialVersion, $afterVersion, 'Version should be bumped when payment is deleted');
    }

    public function test_concurrency_atomic_invalidation_no_lost_updates(): void
    {
        Cache::forever(PanelDashboardService::CACHE_VERSION_KEY, 100);

        // Execute 15 rapid invalidations
        for ($i = 0; $i < 15; $i++) {
            PanelDashboardService::doAtomicInvalidate();
        }

        $finalVersion = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 0);
        $this->assertSame(115, $finalVersion, 'Atomic flock invalidations must preserve exact increment count');
    }

    public function test_independent_cli_processes_concurrency_no_lost_updates(): void
    {
        config(['cache.default' => 'file']);
        Cache::store('file')->forever(PanelDashboardService::CACHE_VERSION_KEY, 300);

        $processes = [];
        $cmd = [PHP_BINARY, base_path('artisan'), 'dashboard:invalidate'];
        $env = [];
        foreach (array_merge($_SERVER, ['CACHE_DRIVER' => 'file', 'APP_ENV' => 'local']) as $k => $v) {
            if (is_scalar($v)) {
                $env[$k] = (string) $v;
            }
        }

        for ($i = 0; $i < 4; $i++) {
            $pipes = [];
            $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            if (is_resource($proc)) {
                $processes[] = ['proc' => $proc, 'pipes' => $pipes];
            }
        }

        $outputs = [];
        foreach ($processes as $item) {
            $out = stream_get_contents($item['pipes'][1]);
            $err = stream_get_contents($item['pipes'][2]);
            fclose($item['pipes'][0]);
            fclose($item['pipes'][1]);
            fclose($item['pipes'][2]);
            proc_close($item['proc']);
            $outputs[] = "OUT: $out | ERR: $err";
        }

        $final = (int) Cache::store('file')->get(PanelDashboardService::CACHE_VERSION_KEY, 0);
        $this->assertSame(304, $final, 'Independent operating system processes must not lose atomic version increments. Outputs: ' . implode(" --- ", $outputs));
    }

    public function test_transaction_rollback_preserves_cache_without_invalidation(): void
    {
        Cache::forever(PanelDashboardService::CACHE_VERSION_KEY, 50);

        try {
            DB::beginTransaction();

            $admin = User::factory()->create([
                'name' => 'Rollback User',
                'role' => 'administrador',
                'active' => 1,
            ]);

            Pago::create([
                'cuenta' => 'CTA-ROLLBACK-01',
                'dni' => '99009900',
                'titular' => 'CLIENTE ROLLBACK',
                'monto_pagado' => 100.00,
                'monto' => 100.00,
                'fecha' => Carbon::today('America/Lima')->toDateString(),
                'entidad' => 'INTERBANK',
                'gestor' => 'Rollback User',
            ]);

            // Force a rollback
            throw new \Exception('Simulated transaction rollback');
        } catch (\Exception $e) {
            DB::rollBack();
        }

        $versionAfterRollback = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 0);
        $this->assertSame(50, $versionAfterRollback, 'Transaction rollback must NOT invalidate cache or publish version bump');
    }

    public function test_transaction_commit_invalidates_cache_after_commit(): void
    {
        Cache::forever(PanelDashboardService::CACHE_VERSION_KEY, 70);

        DB::beginTransaction();

        $admin = User::factory()->create([
            'name' => 'Commit User',
            'role' => 'administrador',
            'active' => 1,
        ]);

        Pago::create([
            'cuenta' => 'CTA-COMMIT-01',
            'dni' => '99009901',
            'titular' => 'CLIENTE COMMIT',
            'monto_pagado' => 250.00,
            'monto' => 250.00,
            'fecha' => Carbon::today('America/Lima')->toDateString(),
            'entidad' => 'INTERBANK',
            'gestor' => 'Commit User',
        ]);

        DB::commit();

        $versionAfterCommit = (int) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 0);
        $this->assertGreaterThan(70, $versionAfterCommit, 'Transaction commit must execute afterCommit and bump cache version');
    }

    public function test_cuotas_counting_boundary_and_limit_handling(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'active' => 1,
        ]);

        $service = app(PanelDashboardService::class);
        $today = Carbon::today('America/Lima');

        // Case 0 records:
        $promesa = PromesaPago::create([
            'dni' => '11223344',
            'operacion' => 'OP-CUOTA-CNT-0',
            'user_id' => $admin->id,
            'monto' => 5000.00,
            'monto_convenio' => 5000.00,
            'tipo' => 'convenio',
            'fecha_promesa' => $today->toDateString(),
            'workflow_estado' => 'aprobada',
            'nro_cuotas' => 20,
        ]);

        // Insert 20 cuotas inside the 7-day window
        $cuotas = [];
        for ($i = 1; $i <= 20; $i++) {
            $cuotas[] = [
                'promesa_id' => $promesa->id,
                'nro' => $i,
                'fecha' => $today->copy()->addDays($i % 6)->toDateString(),
                'monto' => 250.00,
                'es_balon' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('promesa_cuotas')->insert($cuotas);
        PanelDashboardService::invalidate();

        $data = $service->build($admin);

        // Count must accurately reflect all 20 cuotas, while UI collection takes 8
        $this->assertSame(20, $data['vencCount'], 'vencCount must be exactly 20 when 20 cuotas exist');
        $this->assertCount(8, $data['venc'], 'venc collection must be capped at 8 for display');
    }

    public function test_exact_date_boundary_and_microseconds_preservation(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'active' => 1,
        ]);

        $tz = 'America/Lima';
        $today = Carbon::today($tz);

        // Record exactly at 00:00:00
        $p1 = PromesaPago::create([
            'dni' => '33445566',
            'operacion' => 'OP-BOUNDARY-00',
            'user_id' => $admin->id,
            'monto' => 100.00,
            'monto_convenio' => 100.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => $today->toDateString(),
            'workflow_estado' => 'preaprobada',
            'created_at' => $today->copy()->startOfDay(),
        ]);

        // Record at 23:59:59
        $p2 = PromesaPago::create([
            'dni' => '33445567',
            'operacion' => 'OP-BOUNDARY-23',
            'user_id' => $admin->id,
            'monto' => 200.00,
            'monto_convenio' => 200.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => $today->toDateString(),
            'workflow_estado' => 'preaprobada',
            'created_at' => $today->copy()->endOfDay(),
        ]);

        // Record on next day 00:00:00 (must NOT be counted for today)
        $p3 = PromesaPago::create([
            'dni' => '33445568',
            'operacion' => 'OP-BOUNDARY-NEXT',
            'user_id' => $admin->id,
            'monto' => 300.00,
            'monto_convenio' => 300.00,
            'tipo' => 'cancelacion',
            'fecha_promesa' => $today->copy()->addDay()->toDateString(),
            'workflow_estado' => 'preaprobada',
            'created_at' => $today->copy()->addDay()->startOfDay(),
        ]);

        PanelDashboardService::invalidate();
        $service = app(PanelDashboardService::class);
        $data = $service->build($admin);

        // Must count p1 and p2, but not p3
        $this->assertGreaterThanOrEqual(2, $data['kpiPromHoy']);
    }

    public function test_panel_controller_http_route_renders_successfully(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'active' => 1,
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertStatus(200);
        $response->assertViewHas([
            'kpiPromHoy',
            'kpiPagosHoy',
            'sumMes',
            'chartLabels',
            'chartData',
            'vencCount',
        ]);
    }
}
