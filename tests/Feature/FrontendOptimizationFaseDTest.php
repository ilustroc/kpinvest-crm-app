<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontendOptimizationFaseDTest extends TestCase
{
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();

        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath, 'El manifest.json de Vite debe existir en public/build/manifest.json');
        $this->manifest = json_decode(File::get($manifestPath), true);
        $this->assertIsArray($this->manifest, 'El manifest.json debe ser un JSON válido');
    }

    public function test_vite_manifest_contains_expected_entries_and_dynamic_chunks(): void
    {
        $this->assertArrayHasKey('resources/js/app.js', $this->manifest);
        $this->assertArrayHasKey('resources/js/login.js', $this->manifest);
        $this->assertArrayHasKey('resources/css/app.css', $this->manifest);

        $expectedDynamicEntries = [
            'resources/js/modules/dashboard/stats.js',
            'resources/js/modules/panel/resumen.js',
            'resources/js/modules/clientes/show.js',
            'resources/js/modules/autorizacion/index.js',
            'resources/js/modules/admin/users.js',
            'resources/js/modules/integracion/imports.js',
            'resources/js/modules/reportes/pagos.js',
            'resources/js/modules/reportes/promesas.js',
            'resources/js/modules/reportes/cna.js',
            'resources/js/modules/auth/login.js',
        ];

        foreach ($expectedDynamicEntries as $entry) {
            $this->assertArrayHasKey($entry, $this->manifest, "El módulo dinámico {$entry} debe existir en el manifest");
            $this->assertTrue($this->manifest[$entry]['isDynamicEntry'] ?? false, "El módulo {$entry} debe estar marcado como isDynamicEntry");
        }
    }

    public function test_chartjs_is_isolated_in_vendor_chart_chunk(): void
    {
        // Buscar chunk vendor-chart
        $vendorChartKey = null;
        foreach ($this->manifest as $key => $entry) {
            if (str_starts_with($key, '_vendor-chart-')) {
                $vendorChartKey = $key;
                break;
            }
        }

        $this->assertNotNull($vendorChartKey, 'Debe existir un chunk independiente vendor-chart en el manifest');

        $chartFile = public_path('build/' . $this->manifest[$vendorChartKey]['file']);
        $this->assertFileExists($chartFile);
        $chartSizeKb = filesize($chartFile) / 1024;
        $this->assertGreaterThan(150, $chartSizeKb, 'Chart.js vendor chunk debe contener la librería completa');

        // Solo dashboard/stats y panel/resumen deben importar el vendor-chart
        $modulesWithChart = [];
        $modulesWithoutChart = [
            'resources/js/app.js',
            'resources/js/login.js',
            'resources/js/modules/clientes/show.js',
            'resources/js/modules/autorizacion/index.js',
            'resources/js/modules/admin/users.js',
            'resources/js/modules/integracion/imports.js',
            'resources/js/modules/reportes/pagos.js',
            'resources/js/modules/reportes/promesas.js',
            'resources/js/modules/reportes/cna.js',
            'resources/js/modules/auth/login.js',
        ];

        foreach ($this->manifest as $key => $entry) {
            $imports = $entry['imports'] ?? [];
            if (in_array($vendorChartKey, $imports, true)) {
                $modulesWithChart[] = $key;
            }
        }

        $this->assertContains('resources/js/modules/dashboard/stats.js', $modulesWithChart);
        $this->assertContains('resources/js/modules/panel/resumen.js', $modulesWithChart);

        foreach ($modulesWithoutChart as $moduleKey) {
            $this->assertNotContains(
                $moduleKey,
                $modulesWithChart,
                "El módulo {$moduleKey} NO debe importar vendor-chart directamente"
            );
        }
    }

    public function test_login_page_renders_isolated_login_bundle_without_chartjs(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $content = $response->getContent();

        // Debe cargar login.js y app.css
        $loginEntry = $this->manifest['resources/js/login.js']['file'];
        $this->assertStringContainsString($loginEntry, $content, 'La vista de login debe cargar login.js');

        // NO debe cargar app.js
        $appEntry = $this->manifest['resources/js/app.js']['file'];
        $this->assertStringNotContainsString($appEntry, $content, 'La vista de login NO debe cargar app.js');

        // NO debe cargar vendor-chart
        $vendorChartKey = null;
        foreach ($this->manifest as $key => $entry) {
            if (str_starts_with($key, '_vendor-chart-')) {
                $vendorChartKey = $key;
                break;
            }
        }
        $vendorChartFile = $this->manifest[$vendorChartKey]['file'];
        $this->assertStringNotContainsString($vendorChartFile, $content, 'La vista de login NO debe incluir el chunk de Chart.js');
    }

    public function test_bundle_sizes_meet_optimization_targets(): void
    {
        // 1. Bundle de Login
        $loginFile = public_path('build/' . $this->manifest['resources/js/login.js']['file']);
        $loginSizeKb = filesize($loginFile) / 1024;
        $this->assertLessThan(5, $loginSizeKb, 'El entry point login.js debe pesar menos de 5 KB');

        // 2. Bundle base de App (layout + utilidades compartidas)
        $appFile = public_path('build/' . $this->manifest['resources/js/app.js']['file']);
        $appSizeKb = filesize($appFile) / 1024;
        $this->assertLessThan(25, $appSizeKb, 'El entry point app.js optimizado debe pesar menos de 25 KB');

        // 3. Chunks de módulos específicos deben ser ligeros (< 25 KB cada uno)
        $screenChunks = [
            'resources/js/modules/admin/users.js',
            'resources/js/modules/dashboard/stats.js',
            'resources/js/modules/panel/resumen.js',
            'resources/js/modules/integracion/imports.js',
            'resources/js/modules/reportes/pagos.js',
            'resources/js/modules/reportes/promesas.js',
            'resources/js/modules/reportes/cna.js',
        ];

        foreach ($screenChunks as $chunkKey) {
            $file = public_path('build/' . $this->manifest[$chunkKey]['file']);
            $sizeKb = filesize($file) / 1024;
            $this->assertLessThan(15, $sizeKb, "El chunk {$chunkKey} debe pesar menos de 15 KB");
        }
    }

    public function test_authenticated_screens_render_correct_selectors_for_dynamic_loading(): void
    {
        // Crear usuario de prueba en testing
        DB::table('users')->delete();
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin_frontend@kpinvest.pe',
            'password' => Hash::make('secret123'),
            'role' => 'administrador',
            'active' => 1,
        ]);

        $this->actingAs($admin);

        // 1. Panel Resumen
        $resPanel = $this->get('/');
        $resPanel->assertStatus(200);
        $resPanel->assertSee('data-module="panel-resumen"', false);

        // 2. Dashboard
        $resDash = $this->get('/dashboard');
        $resDash->assertStatus(200);
        $resDash->assertSee('data-dashboard-page', false);

        // 3. Clientes Show
        DB::table('clientes_cuentas')->insert([
            'numdoc' => '12345678',
            'nombre' => 'Cliente Frontend Test',
            'operacion' => 'OP-FRONT-01',
            'entidad' => 'BCP',
            'producto' => 'PRESTAMO',
            'cosecha' => '2023',
            'cuenta' => 'CTA-FRONT-01',
            'deuda_capital' => 1000.00,
            'deuda_total' => 1200.00,
        ]);
        $resCliente = $this->get('/clientes/12345678');
        $resCliente->assertStatus(200);
        $resCliente->assertSee('data-module="clientes-show"', false);

        // 4. Autorización
        $resAuto = $this->get('/autorizacion');
        $resAuto->assertStatus(200);
        $resAuto->assertSee('data-module="autorizacion-index"', false);

        // 5. Reportes CNA
        $resCna = $this->get('/reportes/cna');
        $resCna->assertStatus(200);
        $resCna->assertSee('data-module="reportes-cna"', false);

        // 6. Reportes Pagos
        $resPagos = $this->get('/reportes/pagos');
        $resPagos->assertStatus(200);
        $resPagos->assertSee('data-module="reportes-pagos"', false);

        // 7. Reportes Promesas
        $resPromesas = $this->get('/reportes/promesas');
        $resPromesas->assertStatus(200);
        $resPromesas->assertSee('data-module="reportes-promesas"', false);

        // 8. Administración Usuarios
        $resAdmin = $this->get('/administracion');
        $resAdmin->assertStatus(200);
        $resAdmin->assertSee('data-admin-users-page', false);

        // Todas las vistas autenticadas deben cargar app.js pero NO vendor-chart en su HTML estático
        $vendorChartKey = null;
        foreach ($this->manifest as $key => $entry) {
            if (str_starts_with($key, '_vendor-chart-')) {
                $vendorChartKey = $key;
                break;
            }
        }
        $vendorChartFile = $this->manifest[$vendorChartKey]['file'];

        // En las páginas que NO tienen gráficas (clientes, autorización, reportes, admin), nunca debe haber vendor-chart en head
        $resCliente->assertDontSee($vendorChartFile);
        $resAuto->assertDontSee($vendorChartFile);
        $resCna->assertDontSee($vendorChartFile);
        $resPagos->assertDontSee($vendorChartFile);
        $resPromesas->assertDontSee($vendorChartFile);
        $resAdmin->assertDontSee($vendorChartFile);
    }
}
