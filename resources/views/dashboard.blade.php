<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    Panel Principal de Pañol
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">
                    Área de Informática y Telecomunicaciones &bull; Sede Temuco
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                    <span class="w-2 h-2 rounded-full bg-red-600 me-2 animate-pulse"></span>
                    Rol: {{ ucfirst(str_replace('_', ' ', auth()->user()->rol)) }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Banner de Bienvenida -->
            <div class="bg-gradient-to-r from-red-800 to-red-950 rounded-2xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
                <div class="relative z-10 max-w-2xl">
                    <span class="text-xs font-bold uppercase tracking-wider text-red-200 bg-white/10 px-3 py-1 rounded-full border border-white/15">
                        Bienvenido(a) al Sistema
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold mt-3 tracking-tight">
                        Hola, {{ auth()->user()->name }}
                    </h2>
                    <p class="mt-2 text-sm sm:text-base text-red-100/90 leading-relaxed">
                        Accede a los módulos autorizados para gestionar materiales, activos y trazabilidad operativa de laboratorios.
                    </p>
                </div>
                <div class="absolute right-0 bottom-0 top-0 w-1/3 opacity-10 pointer-events-none hidden md:block">
                    <x-application-logo class="h-full w-full object-contain" />
                </div>
            </div>

            <!-- Accesos Rápidos según Rol Institucional -->
            <div>
                <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    Módulos Operativos Disponibles
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @if(auth()->user()->rol === 'docente')
                        <!-- Módulo Docente: Mis Solicitudes -->
                        <a href="{{ route('docente.solicitudes') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-red-50 text-red-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Mis Solicitudes de Préstamo
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Formular nuevas solicitudes de equipos o insumos y revisar estados de aprobación en tiempo real.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Ir al módulo &rarr;
                            </span>
                        </a>

                        <!-- Módulo Docente: Catálogo de Equipos -->
                        <a href="{{ route('docente.equipos') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Catálogo de Activos
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Consultar especificaciones y disponibilidad de instrumental, computadores y kits de telecomunicaciones.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Explorar catálogo &rarr;
                            </span>
                        </a>
                    @else
                        <!-- Módulo Pañol: Despacho y Devoluciones -->
                        <a href="{{ route('panol.despacho') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-red-50 text-red-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Despacho y Devoluciones
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Escaneo con código de barras, entrega de materiales a docentes/alumnos y recepción conforme.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Gestionar entregas &rarr;
                            </span>
                        </a>

                        <!-- Módulo Pañol: Inventario y Existencias -->
                        <a href="{{ route('panol.inventario') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Inventario de Consumibles
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Control de existencias de insumos, monitoreo de stock crítico y balance del kardex.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Ver inventario &rarr;
                            </span>
                        </a>

                        <!-- Módulo Pañol: Equipos Físicos -->
                        <a href="{{ route('panol.equipos') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Fichas y Trazabilidad de Equipos
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Catálogo individualizado con números de serie, marcas, modelos y fotografías de respaldo.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Administrar equipos &rarr;
                            </span>
                        </a>

                        <!-- Módulo Pañol: Mantenimientos y Fallas -->
                        <a href="{{ route('panol.mantenimientos') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Mantenimientos y Diagnósticos
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Planificación preventiva, recepción de reportes de daño de docentes e historial técnico.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Plan de mantenimiento &rarr;
                            </span>
                        </a>

                        <!-- Módulo Pañol: Compras y OCR -->
                        <a href="{{ route('panol.compras') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Compras, Adquisiciones y OCR
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Registro documental, carga asistida por OCR de facturas y afectación de presupuesto de carrera.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Ingresos y compras &rarr;
                            </span>
                        </a>

                        <!-- Módulo Pañol: Etiquetas de Código de Barras -->
                        <a href="{{ route('panol.etiquetas') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Impresión de Etiquetas
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Generación masiva de códigos de barras lineales Code128 para rotulación física de materiales.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Generar etiquetas &rarr;
                            </span>
                        </a>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
