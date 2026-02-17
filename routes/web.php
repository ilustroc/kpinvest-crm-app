<?php

use Illuminate\Support\Facades\Route;

// Importación de Controladores Normalizados
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteLookupController;
use App\Http\Controllers\PromesaController;
use App\Http\Controllers\PromesaPdfController;
use App\Http\Controllers\CnaController;
use App\Http\Controllers\AutorizacionController;
use App\Http\Controllers\AdminUsersController;

// Reportes Normalizados
use App\Http\Controllers\ReporteCnaController;
use App\Http\Controllers\ReportePagosController;
use App\Http\Controllers\ReportePromesasController;

// Integraciones Normalizadas
use App\Http\Controllers\IntegracionDataController;
use App\Http\Controllers\IntegracionAsignacionController;
use App\Http\Controllers\IntegracionCcdController;
use App\Http\Controllers\IntegracionPagosController;

/*
|--------------------------------------------------------------------------
| Rutas Públicas (Guest)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (Auth & Active)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {

    // Inicio y Dashboard
    Route::get('/', [PanelController::class, 'index'])->name('panel');
    Route::redirect('/panel', '/');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /* --- Clientes --- */
    Route::prefix('clientes')->middleware('block.cliente')->name('clientes.')->group(function () {
        Route::get('/suggest', [ClienteLookupController::class, 'suggest'])->name('suggest');
        Route::get('/lookup', [ClienteLookupController::class, 'quickLookup'])->name('quick');
        Route::get('/{dni}', [ClienteController::class, 'show'])->name('show');
        Route::post('/{dni}/promesas', [PromesaController::class, 'store'])->name('promesas.store');
        Route::post('/{dni}/cnas', [CnaController::class, 'store'])->name('cna.store');
        Route::post('/{dni}/pagos/delete', [ClienteController::class, 'deletePagos'])->name('pagos.delete');
    });

    /* --- Reportes (Normalizados) --- */
    Route::prefix('reportes')->name('reportes.')->group(function () {
        // CNA
        Route::controller(ReporteCnaController::class)->prefix('cna')->group(function () {
            Route::get('/', 'index')->name('cna');
            Route::get('/facets', 'facets')->name('cna.facets');
            Route::get('/export', 'export')->name('cna.export');
        });
        // Pagos
        Route::controller(ReportePagosController::class)->prefix('pagos')->group(function () {
            Route::get('/', 'index')->name('pagos');
            Route::get('/facets', 'facets')->name('pagos.facets');
            Route::get('/export', 'export')->name('pagos.export');
        });
        // Promesas (PDP)
        Route::controller(ReportePromesasController::class)->prefix('promesas')->group(function () {
            Route::get('/', 'index')->name('pdp');
            Route::get('/facets', 'facets')->name('pdp.facets');
            Route::get('/export', 'export')->name('pdp.export');
        });
    });

    /* --- Autorizaciones & Acciones --- */
    Route::middleware('role:administrador,supervisor')->group(function () {
        Route::get('/autorizacion', [AutorizacionController::class, 'index'])->name('autorizacion');
        Route::get('/autorizacion/pagos/{dni}', [AutorizacionController::class, 'pagosDni'])->name('autorizacion.pagos');

        // Promesas
        Route::post('/autorizacion/{promesa}/preaprobar', [AutorizacionController::class, 'preaprobar'])->name('autorizacion.preaprobar');
        Route::post('/autorizacion/{promesa}/rechazar-sup', [AutorizacionController::class, 'rechazarSup'])->name('autorizacion.rechazar.sup');
        Route::post('/autorizacion/{promesa}/aprobar', [AutorizacionController::class, 'aprobar'])->name('autorizacion.aprobar');
        Route::post('/autorizacion/{promesa}/rechazar-admin', [AutorizacionController::class, 'rechazarAdmin'])->name('autorizacion.rechazar.admin');

        // CNA
        Route::post('/cna/{cna}/preaprobar', [CnaController::class, 'preaprobar'])->name('cna.preaprobar');
        Route::post('/cna/{cna}/rechazar-sup', [CnaController::class, 'rechazarSup'])->name('cna.rechazar.sup');
        Route::post('/cna/{cna}/aprobar', [CnaController::class, 'aprobar'])->name('cna.aprobar');
        Route::post('/cna/{cna}/rechazar-admin', [CnaController::class, 'rechazarAdmin'])->name('cna.rechazar.admin');
    });

    // Documentos (PDF/Docx)
    Route::get('/promesas/{promesa}/acuerdo', [PromesaPdfController::class, 'acuerdo'])->name('promesas.acuerdo');
    Route::middleware('role:administrador,supervisor,asesor,soporte')->group(function () {
        Route::get('/cna/{id}/pdf', [CnaController::class, 'pdf'])->name('cna.pdf');
        Route::get('/cna/{id}/docx', [CnaController::class, 'docx'])->name('cna.docx');
    });

    /* --- Integración (Nombres Normalizados) --- */
    Route::middleware('role:administrador,supervisor,soporte')->prefix('integracion')->name('integracion.')->group(function () {
        
        Route::controller(IntegracionDataController::class)->prefix('data')->group(function () {
            Route::get('/', 'index')->name('data.index');
            Route::get('/template', 'template')->name('data.template');
            Route::post('/import', 'import')->name('data.import');
        });

        Route::controller(IntegracionAsignacionController::class)->prefix('asignacion')->group(function () {
            Route::get('/', 'index')->name('asignacion.index');
            Route::get('/template', 'template')->name('asignacion.template');
            Route::post('/import', 'import')->name('asignacion.import');
        });

        Route::controller(IntegracionCcdController::class)->prefix('ccd')->group(function () {
            Route::get('/', 'index')->name('ccd.index');
            Route::get('/template', 'template')->name('ccd.template');
            Route::post('/import', 'import')->name('ccd.import');
        });

        Route::controller(IntegracionPagosController::class)->prefix('pagos')->group(function () {
            Route::get('/', 'index')->name('pagos.index');
            Route::get('/template', 'template')->name('pagos.template');
            Route::post('/import', 'import')->name('pagos.import');
        });
    });

    /* --- Administración de Usuarios --- */
    Route::middleware('role:administrador,supervisor,soporte')->prefix('administracion')->name('administracion.')->group(function () {
        Route::get('/', [AdminUsersController::class, 'index'])->name('index');
        Route::post('/usuarios', [AdminUsersController::class, 'store'])->name('usuarios.store');
        Route::patch('/usuarios/{user}/toggle', [AdminUsersController::class, 'toggle'])->name('usuarios.toggle');
        Route::patch('/usuarios/{user}/password', [AdminUsersController::class, 'updatePassword'])->name('usuarios.password');
    });
});

/* --- Redirecciones de Compatibilidad --- */
Route::any('/index.php', fn () => redirect('/'));
Route::get('/home', fn () => redirect('/'));