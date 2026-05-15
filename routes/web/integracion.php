<?php

use App\Http\Controllers\IntegracionAsignacionController;
use App\Http\Controllers\IntegracionCcdController;
use App\Http\Controllers\IntegracionDataController;
use App\Http\Controllers\IntegracionPagosController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:access-integracion')
    ->prefix('integracion')
    ->name('integracion.')
    ->group(function () {
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
