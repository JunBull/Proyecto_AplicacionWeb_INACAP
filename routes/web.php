<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Rutas protegidas por Rol según Matriz de Permisos (docs/especificacion_requerimientos_sistema.md - Sección 2.2)

// Módulo Administración: Exclusivo Administrador
Route::middleware(['auth', 'role:administrador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/usuarios', fn () => response()->json(['status' => 'ok', 'modulo' => 'gestion_usuarios']))->name('usuarios');
    Route::get('/carreras', fn () => response()->json(['status' => 'ok', 'modulo' => 'gestion_carreras']))->name('carreras');
    Route::get('/parametros', fn () => response()->json(['status' => 'ok', 'modulo' => 'configuracion_parametros']))->name('parametros');
    Route::get('/documentos/{id}', fn ($id) => response()->json(['status' => 'ok', 'documento_id' => $id]))->name('documentos.show');
});

// Módulo Pañol: Encargado de Pañol y Administrador
Route::middleware(['auth', 'role:encargado_panol,administrador'])->prefix('panol')->name('panol.')->group(function () {
    Route::get('/despacho', fn () => response()->json(['status' => 'ok', 'modulo' => 'despacho_meson']))->name('despacho');
    Route::get('/inventario', fn () => response()->json(['status' => 'ok', 'modulo' => 'inventario']))->name('inventario');
});

// Módulo Docente: Docente, Pañolero y Administrador
Route::middleware(['auth', 'role:docente,encargado_panol,administrador'])->prefix('docente')->name('docente.')->group(function () {
    Route::get('/solicitudes', fn () => response()->json(['status' => 'ok', 'modulo' => 'solicitudes_prestamo']))->name('solicitudes');
});

require __DIR__.'/auth.php';
