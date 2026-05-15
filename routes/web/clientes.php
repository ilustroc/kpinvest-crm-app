<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteLookupController;
use App\Http\Controllers\CnaController;
use App\Http\Controllers\PromesaController;
use Illuminate\Support\Facades\Route;

Route::prefix('clientes')
    ->middleware('block.cliente')
    ->name('clientes.')
    ->group(function () {
        Route::get('/suggest', [ClienteLookupController::class, 'suggest'])->name('suggest');
        Route::get('/lookup', [ClienteLookupController::class, 'quickLookup'])->name('quick');
        Route::get('/{dni}', [ClienteController::class, 'show'])->name('show');
        Route::post('/{dni}/promesas', [PromesaController::class, 'store'])->name('promesas.store');
        Route::post('/{dni}/cnas', [CnaController::class, 'store'])->name('cna.store');
        Route::post('/{dni}/pagos/delete', [ClienteController::class, 'deletePagos'])->name('pagos.delete');
    });
