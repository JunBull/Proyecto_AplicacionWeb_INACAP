<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

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
    Volt::route('/bajas', 'pages.bajas.index')->name('bajas');
    Volt::route('/proveedores', 'pages.proveedores.index')->name('proveedores');
    Volt::route('/cotizaciones', 'pages.cotizaciones.index')->name('cotizaciones');
    Volt::route('/compras', 'pages.compras.index')->name('compras');
});

// Módulo Pañol: Encargado de Pañol y Administrador
Route::middleware(['auth', 'role:encargado_panol,administrador'])->prefix('panol')->name('panol.')->group(function () {
    Volt::route('/despacho', 'pages.despacho.index')->name('despacho');
    Volt::route('/equipos', 'pages.equipos.index')->name('equipos');
    Volt::route('/inventario', 'pages.inventario.index')->name('inventario');
    Volt::route('/mantenimientos', 'pages.mantenimientos.index')->name('mantenimientos');
    Volt::route('/bajas', 'pages.bajas.index')->name('bajas');
    Volt::route('/etiquetas', 'pages.etiquetas.index')->name('etiquetas');
    Volt::route('/proveedores', 'pages.proveedores.index')->name('proveedores');
    Volt::route('/cotizaciones', 'pages.cotizaciones.index')->name('cotizaciones');
    Volt::route('/compras', 'pages.compras.index')->name('compras');
});

// Módulo Docente: Docente, Pañolero y Administrador
Route::middleware(['auth', 'role:docente,encargado_panol,administrador'])->prefix('docente')->name('docente.')->group(function () {
    Volt::route('/solicitudes', 'pages.solicitudes.index')->name('solicitudes');
    Volt::route('/equipos', 'pages.equipos.index')->name('equipos');
});

require __DIR__.'/auth.php';
