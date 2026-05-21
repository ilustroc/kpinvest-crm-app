<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notificaciones.index');
Route::get('/notificaciones/unread-count', [NotificationController::class, 'unreadCount'])->name('notificaciones.unread-count');
Route::post('/notificaciones/leer-todas', [NotificationController::class, 'markAllAsRead'])->name('notificaciones.leer-todas');
Route::post('/notificaciones/{id}/leer', [NotificationController::class, 'markAsRead'])->name('notificaciones.leer');
