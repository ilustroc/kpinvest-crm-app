<?php

use App\Http\Controllers\AutorizacionController;
use App\Http\Controllers\PromesaPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:administrador,supervisor')->group(function () {
    Route::get('/autorizacion', [AutorizacionController::class, 'index'])->name('autorizacion');
    Route::get('/autorizacion/pagos/{dni}', [AutorizacionController::class, 'pagosDni'])->name('autorizacion.pagos');

    Route::post('/autorizacion/{promesa}/preaprobar', [AutorizacionController::class, 'preaprobar'])->name('autorizacion.preaprobar');
    Route::post('/autorizacion/{promesa}/rechazar-sup', [AutorizacionController::class, 'rechazarSup'])->name('autorizacion.rechazar.sup');
    Route::post('/autorizacion/{promesa}/aprobar', [AutorizacionController::class, 'aprobar'])->name('autorizacion.aprobar');
    Route::post('/autorizacion/{promesa}/rechazar-admin', [AutorizacionController::class, 'rechazarAdmin'])->name('autorizacion.rechazar.admin');
});

Route::get('/promesas/{promesa}/acuerdo', [PromesaPdfController::class, 'acuerdo'])->name('promesas.acuerdo');
