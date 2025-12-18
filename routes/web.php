<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteLookupController;
use App\Http\Controllers\PromesaController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ReporteCnaController;
use App\Http\Controllers\ReportePagosController;
use App\Http\Controllers\AdminUsersController;
use App\Http\Controllers\PlaceholdersPagosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientesCargaController;
use App\Http\Controllers\AutorizacionController;
use App\Http\Controllers\PromesaPdfController;
use App\Http\Controllers\CnaController;
use App\Http\Controllers\ReportePromesasController;
use App\Http\Controllers\IntegracionCcdController;
use App\Http\Controllers\IntegracionAsignarController;

// Rutas Web
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

/*
|--------------------------------------------------------------------------
| Salir (solo auth)
|--------------------------------------------------------------------------
*/
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Autenticados
|--------------------------------------------------------------------------
*/
Route::middleware(['auth','active'])->group(function () {

    // Panel
    Route::get('/', [PanelController::class, 'index'])->name('panel');
    Route::redirect('/panel', '/');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class,'index'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Clientes
    |--------------------------------------------------------------------------
    */
    Route::prefix('clientes')->group(function () {
        Route::get('/suggest', [ClienteLookupController::class,'suggest'])->name('clientes.suggest');
        Route::get('/lookup',  [ClienteLookupController::class,'quickLookup'])->name('clientes.quick');
        Route::get('/{dni}',   [ClienteController::class,'show'])->name('clientes.show');
    
        Route::post('/{dni}/promesas', [PromesaController::class,'store'])->name('clientes.promesas.store');
        Route::post('/{dni}/cnas',     [CnaController::class, 'store'])->name('clientes.cna.store');
        Route::post('/{dni}/pagos/delete', [ClienteController::class, 'deletePagos'])->name('clientes.pagos.delete');
    });

    /*
    |--------------------------------------------------------------------------
    | Reportes
    |--------------------------------------------------------------------------
    */
    Route::prefix('reportes')->group(function () {
        Route::get('/cna',        [ReporteCnaController::class, 'index'])->name('reportes.cna');
        Route::get('/cna/export', [ReporteCnaController::class, 'export'])->name('reportes.cna.export');

        Route::get('/pagos',        [ReportePagosController::class, 'index'])->name('reportes.pagos');
        Route::get('/pagos/export', [ReportePagosController::class, 'export'])->name('reportes.pagos.export');

        Route::get('/pdp',        [ReportePromesasController::class, 'index'])->name('reportes.pdp');
        Route::get('/pdp/export', [ReportePromesasController::class, 'export'])->name('reportes.pdp.export');
    });

    /*
    |--------------------------------------------------------------------------
    | Autorización (Promesas + CNA)  -> SIN acceso para soporte
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:administrador,supervisor')->group(function () {
        Route::get('/autorizacion', [AutorizacionController::class,'index'])->name('autorizacion');
        Route::get('/autorizacion/pagos/{dni}', [AutorizacionController::class, 'pagosDni'])->name('autorizacion.pagos');

        // Acciones de promesas
        Route::post('/autorizacion/{promesa}/preaprobar',   [AutorizacionController::class,'preaprobar'])->name('autorizacion.preaprobar');
        Route::post('/autorizacion/{promesa}/rechazar-sup', [AutorizacionController::class,'rechazarSup'])->name('autorizacion.rechazar.sup');
        Route::post('/autorizacion/{promesa}/aprobar',        [AutorizacionController::class,'aprobar'])->name('autorizacion.aprobar');
        Route::post('/autorizacion/{promesa}/rechazar-admin', [AutorizacionController::class,'rechazarAdmin'])->name('autorizacion.rechazar.admin');

        // CNA acciones
        Route::post('/cna/{cna}/preaprobar',   [CnaController::class,'preaprobar'])->name('cna.preaprobar');
        Route::post('/cna/{cna}/rechazar-sup', [CnaController::class,'rechazarSup'])->name('cna.rechazar.sup');
        Route::post('/cna/{cna}/aprobar',        [CnaController::class,'aprobar'])->name('cna.aprobar');
        Route::post('/cna/{cna}/rechazar-admin', [CnaController::class,'rechazarAdmin'])->name('cna.rechazar.admin');
    });

    // Ver acuerdo PDF (para cualquiera autenticado)
    Route::get('/promesas/{promesa}/acuerdo', [PromesaPdfController::class, 'acuerdo'])
        ->name('promesas.acuerdo');

    // CNA PDFs/Docx (lectura)
    Route::middleware('role:administrador,supervisor,asesor,soporte')->group(function () {
        Route::get('/cna/{id}/pdf',  [CnaController::class, 'pdf'])->name('cna.pdf');
        Route::get('/cna/{id}/docx', [CnaController::class, 'docx'])->name('cna.docx');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin (Integración + Administración) -> incluye soporte
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:administrador,supervisor,soporte')->group(function () {

        // INTEGRACIÓN: Data maestro de clientes
        Route::view('/integracion/data', 'placeholders.integracion-data')->name('integracion.data');
        Route::get('/integracion/data/clientes/template', [ClientesCargaController::class, 'templateClientesMaster'])->name('integracion.data.clientes.template');
        Route::post('/integracion/data/clientes/import',  [ClientesCargaController::class, 'importClientesMaster'])->name('integracion.data.clientes.import');

        // INTEGRACIÓN: ASIGNAR
        Route::get('/integracion/asignar',           [IntegracionAsignarController::class, 'index'])->name('integracion.asignar');
        Route::get('/integracion/asignar/template',  [IntegracionAsignarController::class, 'template'])->name('integracion.asignar.template');
        Route::post('/integracion/asignar/import',   [IntegracionAsignarController::class, 'import'])->name('integracion.asignar.import');

        // INTEGRACIÓN: CCD
        Route::get('/integracion/ccd',           [IntegracionCcdController::class, 'index'])->name('integracion.ccd');
        Route::get('/integracion/ccd/template',  [IntegracionCcdController::class, 'template'])->name('integracion.ccd.template');
        Route::post('/integracion/ccd/import',   [IntegracionCcdController::class, 'import'])->name('integracion.ccd.import');

        // INTEGRACIÓN: Pagos
        Route::get('/integracion/pagos',          [PlaceholdersPagosController::class, 'index'])->name('integracion.pagos');
        Route::post('/integracion/pagos/import',  [PlaceholdersPagosController::class, 'import'])->name('integracion.pagos.import');
        Route::get('/integracion/pagos/template', [PlaceholdersPagosController::class, 'template'])->name('integracion.pagos.template');

        // ADMINISTRACIÓN DE USUARIOS
        Route::get('/administracion', [AdminUsersController::class, 'index'])
        ->name('administracion');

        Route::post('/administracion/usuarios', [AdminUsersController::class, 'store'])
            ->name('administracion.usuarios.store');

        Route::patch('/administracion/usuarios/{user}/toggle', [AdminUsersController::class,'toggle'])
            ->name('administracion.usuarios.toggle');

        Route::patch('/administracion/usuarios/{user}/password', [AdminUsersController::class,'updatePassword'])
            ->name('administracion.usuarios.password');
    });

    // Zonas por rol (opcionales)
    Route::middleware('role:administrador')->get('/admin', fn () => 'Zona Admin');
    Route::middleware('role:supervisor')->get('/supervisor', fn () => 'Zona Supervisor');
});

/*
|--------------------------------------------------------------------------
| Compatibilidad
|--------------------------------------------------------------------------
*/
Route::any('/index.php', fn () => redirect('/'));
Route::get('/home', fn () => redirect('/'));
