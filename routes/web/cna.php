<?php

use App\Http\Controllers\CnaController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:administrador,supervisor')->group(function () {
    Route::post('/cna/{cna}/preaprobar', [CnaController::class, 'preaprobar'])->name('cna.preaprobar');
    Route::post('/cna/{cna}/rechazar-sup', [CnaController::class, 'rechazarSup'])->name('cna.rechazar.sup');
    Route::post('/cna/{cna}/aprobar', [CnaController::class, 'aprobar'])->name('cna.aprobar');
    Route::post('/cna/{cna}/rechazar-admin', [CnaController::class, 'rechazarAdmin'])->name('cna.rechazar.admin');
});

Route::middleware('role:administrador,supervisor,asesor,soporte')->group(function () {
    Route::get('/cna/{id}/pdf', [CnaController::class, 'pdf'])->name('cna.pdf');
    Route::get('/cna/{id}/docx', [CnaController::class, 'docx'])->name('cna.docx');
});
