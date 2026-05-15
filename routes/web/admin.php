<?php

use App\Http\Controllers\AdminUsersController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:administrador,supervisor,soporte')
    ->prefix('administracion')
    ->name('administracion.')
    ->group(function () {
        Route::get('/', [AdminUsersController::class, 'index'])->name('index');
        Route::post('/usuarios', [AdminUsersController::class, 'store'])->name('usuarios.store');
        Route::patch('/usuarios/{user}/toggle', [AdminUsersController::class, 'toggle'])->name('usuarios.toggle');
        Route::patch('/usuarios/{user}/password', [AdminUsersController::class, 'updatePassword'])->name('usuarios.password');
    });
