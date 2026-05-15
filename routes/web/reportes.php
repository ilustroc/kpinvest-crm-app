<?php

use App\Http\Controllers\ReporteCnaController;
use App\Http\Controllers\ReportePagosController;
use App\Http\Controllers\ReportePromesasController;
use Illuminate\Support\Facades\Route;

Route::prefix('reportes')->name('reportes.')->group(function () {
    Route::controller(ReporteCnaController::class)->prefix('cna')->group(function () {
        Route::get('/', 'index')->name('cna');
        Route::get('/facets', 'facets')->name('cna.facets');
        Route::get('/export', 'export')->name('cna.export');
    });

    Route::controller(ReportePagosController::class)->prefix('pagos')->group(function () {
        Route::get('/', 'index')->name('pagos');
        Route::get('/facets', 'facets')->name('pagos.facets');
        Route::get('/export', 'export')->name('pagos.export');
    });

    Route::controller(ReportePromesasController::class)->prefix('promesas')->group(function () {
        Route::get('/', 'index')->name('pdp');
        Route::get('/facets', 'facets')->name('pdp.facets');
        Route::get('/export', 'export')->name('pdp.export');
    });
});
