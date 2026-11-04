<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteLookupController;
use App\Http\Controllers\CcdDocumentController;
use App\Http\Controllers\CnaController;
use App\Http\Controllers\PromesaController;
use Illuminate\Support\Facades\Route;

Route::prefix('clientes')
    ->middleware('block.cliente')
    ->name('clientes.')
    ->group(function () {
        Route::get('/suggest', [ClienteLookupController::class, 'suggest'])->name('suggest');
        Route::get('/lookup', [ClienteLookupController::class, 'quickLookup'])->name('quick');
        Route::get('/{numdoc}/ccd/{ccdCliente}', [CcdDocumentController::class, 'show'])->name('ccd.show');
        Route::get('/{numdoc}', [ClienteController::class, 'show'])->name('show');
        Route::post('/{numdoc}/promesas', [PromesaController::class, 'store'])->name('promesas.store');
        Route::post('/{numdoc}/cnas', [CnaController::class, 'store'])->name('cna.store');
        Route::get('/{numdoc}/asignaciones', fn (string $numdoc) => redirect()->route('clientes.show', $numdoc));
        Route::post('/{numdoc}/asignaciones', [ClienteController::class, 'storeAsignacion'])->name('asignaciones.store');
        Route::post('/{numdoc}/pagos/delete', [ClienteController::class, 'deletePagos'])->name('pagos.delete');
    });
