<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white border-b border-slate-200 sticky top-0 z-40 w-full shadow-xs">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div class="flex justify-between items-center h-16 w-full gap-2">
            
            <!-- Bloque Izquierdo: Logo y Navegación Principal -->
            <div class="flex items-center gap-3 lg:gap-6 min-w-0">
                
                <!-- Logo Institucional INACAP Oficial -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 rounded-lg transition py-1" wire:navigate aria-label="Ir al Panel Principal de INACAP">
                        <x-application-logo class="block h-8 sm:h-9 lg:h-10 w-auto fill-current text-slate-800 transition" />
                    </a>
                </div>

                <!-- Enlaces y Menús Desplegables Agrupados (Desktop: md en adelante) -->
                <div class="hidden md:flex md:items-center md:gap-x-1 lg:gap-x-2">
                    <!-- Dashboard -->
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate class="px-2.5 py-1.5 text-xs lg:text-sm">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if(auth()->user()->rol !== 'docente')
                        <!-- Desplegable: Operaciones -->
                        @php
                            $isOperacionesActive = request()->routeIs('panol.despacho', 'panol.inventario', 'panol.etiquetas');
                        @endphp
                        <div class="relative inline-flex items-center h-16" x-data="{ openOperaciones: false }" @click.outside="openOperaciones = false" @close.stop="openOperaciones = false">
                            <button 
                                @click="openOperaciones = ! openOperaciones" 
                                type="button" 
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 border-b-2 text-xs lg:text-sm transition duration-150 ease-in-out focus:outline-none rounded-t-md {{ $isOperacionesActive ? 'border-red-700 text-red-700 font-semibold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 font-medium' }}"
                                :aria-expanded="openOperaciones.toString()"
                            >
                                <span>Operaciones</span>
                                <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180 text-red-700': openOperaciones }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div 
                                x-show="openOperaciones" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full z-50 mt-1 w-60 rounded-xl bg-white shadow-xl ring-1 ring-black/5 border border-slate-200/80 p-1.5 focus:outline-none"
                                style="display: none;"
                                @click="openOperaciones = false"
                            >
                                <a href="{{ route('panol.despacho') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.despacho') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.despacho') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Despacho y Devoluciones</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Escaneo y entrega a docentes/alumnos</div>
                                    </div>
                                </a>

                                <a href="{{ route('panol.inventario') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.inventario') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.inventario') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Inventario de Consumibles</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Control físico y stock crítico</div>
                                    </div>
                                </a>

                                <a href="{{ route('panol.etiquetas') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.etiquetas') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.etiquetas') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Etiquetas de Código de Barras</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Impresión masiva Code128</div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Desplegable: Activos -->
                        @php
                            $isActivosActive = request()->routeIs('panol.equipos', 'panol.mantenimientos', 'panol.bajas');
                        @endphp
                        <div class="relative inline-flex items-center h-16" x-data="{ openActivos: false }" @click.outside="openActivos = false" @close.stop="openActivos = false">
                            <button 
                                @click="openActivos = ! openActivos" 
                                type="button" 
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 border-b-2 text-xs lg:text-sm transition duration-150 ease-in-out focus:outline-none rounded-t-md {{ $isActivosActive ? 'border-red-700 text-red-700 font-semibold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 font-medium' }}"
                                :aria-expanded="openActivos.toString()"
                            >
                                <span>Activos</span>
                                <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180 text-red-700': openActivos }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div 
                                x-show="openActivos" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full z-50 mt-1 w-60 rounded-xl bg-white shadow-xl ring-1 ring-black/5 border border-slate-200/80 p-1.5 focus:outline-none"
                                style="display: none;"
                                @click="openActivos = false"
                            >
                                <a href="{{ route('panol.equipos') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.equipos') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.equipos') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Fichas de Equipos</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Catálogo físico e individualizado</div>
                                    </div>
                                </a>

                                <a href="{{ route('panol.mantenimientos') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.mantenimientos') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.mantenimientos') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Mantenimientos y Diagnósticos</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Preventivos y reportes docentes</div>
                                    </div>
                                </a>

                                <a href="{{ route('panol.bajas') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.bajas') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.bajas') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Bajas y Reemplazos</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Resolución y desechos de activos</div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Desplegable: Adquisiciones -->
                        @php
                            $isAdquisicionesActive = request()->routeIs('panol.proveedores', 'panol.cotizaciones', 'panol.compras');
                        @endphp
                        <div class="relative inline-flex items-center h-16" x-data="{ openAdquisiciones: false }" @click.outside="openAdquisiciones = false" @close.stop="openAdquisiciones = false">
                            <button 
                                @click="openAdquisiciones = ! openAdquisiciones" 
                                type="button" 
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 border-b-2 text-xs lg:text-sm transition duration-150 ease-in-out focus:outline-none rounded-t-md {{ $isAdquisicionesActive ? 'border-red-700 text-red-700 font-semibold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 font-medium' }}"
                                :aria-expanded="openAdquisiciones.toString()"
                            >
                                <span>Adquisiciones</span>
                                <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180 text-red-700': openAdquisiciones }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div 
                                x-show="openAdquisiciones" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full z-50 mt-1 w-64 rounded-xl bg-white shadow-xl ring-1 ring-black/5 border border-slate-200/80 p-1.5 focus:outline-none"
                                style="display: none;"
                                @click="openAdquisiciones = false"
                            >
                                <a href="{{ route('panol.proveedores') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.proveedores') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.proveedores') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Proveedores</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Contactos comerciales y rubros</div>
                                    </div>
                                </a>

                                <a href="{{ route('panol.cotizaciones') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.cotizaciones') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.cotizaciones') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Solicitudes de Cotización</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Despacho masivo vía correo electrónico</div>
                                    </div>
                                </a>

                                <a href="{{ route('panol.compras') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('panol.compras') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                    <svg class="w-4 h-4 {{ request()->routeIs('panol.compras') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <div>
                                        <div class="leading-tight">Compras y OCR Documental</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Facturación y presupuesto de carreras</div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Desplegable: Administración (Exclusivo Administrador) -->
                        @if(auth()->user()->rol === 'administrador')
                            @php
                                $isAdminActive = request()->routeIs('admin.*');
                            @endphp
                            <div class="relative inline-flex items-center h-16" x-data="{ openAdmin: false }" @click.outside="openAdmin = false" @close.stop="openAdmin = false">
                                <button 
                                    @click="openAdmin = ! openAdmin" 
                                    type="button" 
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 border-b-2 text-xs lg:text-sm transition duration-150 ease-in-out focus:outline-none rounded-t-md {{ $isAdminActive ? 'border-red-700 text-red-700 font-semibold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 font-medium' }}"
                                    :aria-expanded="openAdmin.toString()"
                                >
                                    <span>Administración</span>
                                    <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180 text-red-700': openAdmin }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div 
                                    x-show="openAdmin" 
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                    class="absolute left-0 top-full z-50 mt-1 w-56 rounded-xl bg-white shadow-xl ring-1 ring-black/5 border border-slate-200/80 p-1.5 focus:outline-none"
                                    style="display: none;"
                                    @click="openAdmin = false"
                                >
                                    <a href="{{ route('admin.usuarios') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('admin.usuarios') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                        <svg class="w-4 h-4 {{ request()->routeIs('admin.usuarios') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <div>
                                            <div class="leading-tight">Gestión de Usuarios</div>
                                            <div class="text-[10px] text-slate-400 font-normal">Cuentas y roles del sistema</div>
                                        </div>
                                    </a>

                                    <a href="{{ route('admin.carreras') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('admin.carreras') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                        <svg class="w-4 h-4 {{ request()->routeIs('admin.carreras') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                        <div>
                                            <div class="leading-tight">Carreras y Presupuesto</div>
                                            <div class="text-[10px] text-slate-400 font-normal">Centros de costo académicos</div>
                                        </div>
                                    </a>

                                    <a href="{{ route('admin.parametros') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-lg transition {{ request()->routeIs('admin.parametros') ? 'bg-red-50 text-red-700 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-medium' }}" wire:navigate>
                                        <svg class="w-4 h-4 {{ request()->routeIs('admin.parametros') ? 'text-red-700' : 'text-slate-400' }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <div>
                                            <div class="leading-tight">Parámetros del Sistema</div>
                                            <div class="text-[10px] text-slate-400 font-normal">Configuración y políticas</div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        @endif
                    @else
                        <!-- Módulo Docente: Enlaces directos pulcros -->
                        <x-nav-link :href="route('docente.solicitudes')" :active="request()->routeIs('docente.solicitudes')" wire:navigate class="px-2.5 py-1.5 text-xs lg:text-sm">
                            Mis Solicitudes
                        </x-nav-link>
                        <x-nav-link :href="route('docente.equipos')" :active="request()->routeIs('docente.equipos')" wire:navigate class="px-2.5 py-1.5 text-xs lg:text-sm">
                            Catálogo de Equipos
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Bloque Derecho: Menú de Usuario (Desktop) Siempre Anclado a la Derecha -->
            <div class="hidden md:flex md:items-center md:ms-auto shrink-0 ps-2">
                <div class="relative" x-data="{ openUser: false }" @click.outside="openUser = false" @close.stop="openUser = false">
                    <button 
                        @click="openUser = ! openUser"
                        type="button"
                        class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-200 text-xs font-medium rounded-lg text-slate-700 bg-white hover:text-slate-900 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition ease-in-out duration-150 shadow-2xs"
                        :aria-expanded="openUser.toString()"
                    >
                        <div class="w-6 h-6 rounded-full bg-red-100 text-red-700 font-bold flex items-center justify-center text-[11px] shrink-0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <span class="font-semibold text-slate-800 max-w-[130px] truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
                            {{ auth()->user()->rol }}
                        </span>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180 text-red-700': openUser }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div 
                        x-show="openUser"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                        class="absolute right-0 top-full z-50 mt-1.5 w-56 rounded-xl bg-white shadow-xl ring-1 ring-black/5 border border-slate-200/80 py-1.5 focus:outline-none"
                        style="display: none;"
                        @click="openUser = false"
                    >
                        <div class="px-4 py-2 border-b border-slate-100 text-xs">
                            <div class="font-semibold text-slate-900 truncate">{{ auth()->user()->name }}</div>
                            <div class="text-[11px] text-slate-500 truncate">{{ auth()->user()->email }}</div>
                        </div>

                        <div class="p-1">
                            <a href="{{ route('profile') }}" class="flex items-center gap-2 w-full px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 hover:text-slate-900 rounded-lg transition font-medium" wire:navigate>
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Mi Perfil
                            </a>

                            <button wire:click="logout" class="flex items-center gap-2 w-full px-3 py-2 text-xs text-red-700 hover:bg-red-50 rounded-lg transition font-medium text-start">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Cerrar Sesión
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón Hamburguesa Móvil (Visible solo en pantallas < md) -->
            <div class="flex items-center md:hidden shrink-0 ms-auto">
                <button 
                    @click="open = ! open" 
                    type="button"
                    class="inline-flex items-center justify-center p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none focus:bg-slate-100 focus:text-slate-700 transition duration-150 ease-in-out" 
                    aria-label="Alternar menú de navegación"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>
    </div>

    <!-- Menú Desplegable Responsivo (Móvil y Tablets pequeñas < md) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden md:hidden border-t border-slate-200 bg-white shadow-lg">
        <div class="pt-2 pb-3 space-y-1 px-4 max-h-[calc(100vh-5rem)] overflow-y-auto">
            
            <!-- Dashboard -->
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @if(auth()->user()->rol !== 'docente')
                <!-- Sección Móvil: Operaciones -->
                <div class="pt-3 pb-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3">Operaciones de Pañol</span>
                </div>
                <x-responsive-nav-link :href="route('panol.despacho')" :active="request()->routeIs('panol.despacho')" wire:navigate>
                    Despacho y Devoluciones
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('panol.inventario')" :active="request()->routeIs('panol.inventario')" wire:navigate>
                    Inventario de Consumibles
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('panol.etiquetas')" :active="request()->routeIs('panol.etiquetas')" wire:navigate>
                    Etiquetas de Código de Barras
                </x-responsive-nav-link>

                <!-- Sección Móvil: Activos -->
                <div class="pt-3 pb-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3">Gestión de Activos</span>
                </div>
                <x-responsive-nav-link :href="route('panol.equipos')" :active="request()->routeIs('panol.equipos')" wire:navigate>
                    Fichas de Equipos
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('panol.mantenimientos')" :active="request()->routeIs('panol.mantenimientos')" wire:navigate>
                    Mantenimientos y Diagnósticos
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('panol.bajas')" :active="request()->routeIs('panol.bajas')" wire:navigate>
                    Bajas y Reemplazos
                </x-responsive-nav-link>

                <!-- Sección Móvil: Adquisiciones -->
                <div class="pt-3 pb-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3">Adquisiciones y Compras</span>
                </div>
                <x-responsive-nav-link :href="route('panol.proveedores')" :active="request()->routeIs('panol.proveedores')" wire:navigate>
                    Proveedores
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('panol.cotizaciones')" :active="request()->routeIs('panol.cotizaciones')" wire:navigate>
                    Solicitudes de Cotización
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('panol.compras')" :active="request()->routeIs('panol.compras')" wire:navigate>
                    Compras y OCR Documental
                </x-responsive-nav-link>

                @if(auth()->user()->rol === 'administrador')
                    <!-- Sección Móvil: Administración -->
                    <div class="pt-3 pb-1">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3">Administración del Sistema</span>
                    </div>
                    <x-responsive-nav-link :href="route('admin.usuarios')" :active="request()->routeIs('admin.usuarios')" wire:navigate>
                        Gestión de Usuarios
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.carreras')" :active="request()->routeIs('admin.carreras')" wire:navigate>
                        Carreras y Presupuesto
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.parametros')" :active="request()->routeIs('admin.parametros')" wire:navigate>
                        Parámetros del Sistema
                    </x-responsive-nav-link>
                @endif
            @else
                <!-- Sección Móvil: Docente -->
                <div class="pt-3 pb-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3">Portal Docente</span>
                </div>
                <x-responsive-nav-link :href="route('docente.solicitudes')" :active="request()->routeIs('docente.solicitudes')" wire:navigate>
                    Mis Solicitudes
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('docente.equipos')" :active="request()->routeIs('docente.equipos')" wire:navigate>
                    Catálogo de Equipos
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Opciones de Usuario Responsivo -->
        <div class="pt-4 pb-3 border-t border-slate-200 bg-slate-50/70 px-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-red-100 text-red-700 font-bold flex items-center justify-center text-xs shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-sm text-slate-800 truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</div>
                </div>
                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full bg-slate-200 text-slate-700">
                    {{ auth()->user()->rol }}
                </span>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Cerrar Sesión -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link class="text-red-700 font-medium">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
