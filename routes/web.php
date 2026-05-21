<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas publicas
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Rutas protegidas por sesion y usuario activo
|--------------------------------------------------------------------------
|
| La Fase 2 de V3 separa las rutas por modulo manteniendo los mismos
| controladores, URLs, nombres de rutas y middlewares.
|
*/
Route::middleware(['auth', 'active'])->group(function () {
    require __DIR__.'/web/dashboard.php';
    require __DIR__.'/web/clientes.php';
    require __DIR__.'/web/reportes.php';
    require __DIR__.'/web/promesas.php';
    require __DIR__.'/web/cna.php';
    require __DIR__.'/web/notificaciones.php';
    require __DIR__.'/web/integracion.php';
    require __DIR__.'/web/admin.php';
});

/*
|--------------------------------------------------------------------------
| Redirecciones de compatibilidad
|--------------------------------------------------------------------------
*/
Route::any('/index.php', fn () => redirect('/'));
Route::get('/home', fn () => redirect('/'));
