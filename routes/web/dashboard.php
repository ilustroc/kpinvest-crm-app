<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PanelController::class, 'index'])->name('panel');
Route::redirect('/panel', '/');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
