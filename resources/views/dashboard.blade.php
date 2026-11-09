<x-app-layout>
    <div class="py-6 sm:py-8">
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

            @if(auth()->user()->rol === 'docente')
                <!-- Sección Docente: Módulos Directos -->
                <div>
                    <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        Módulos
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <!-- Módulo Docente: Solicitud de Materiales -->
                        <a href="{{ route('docente.solicitudes') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-red-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-red-50 text-red-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-red-700 transition">
                                Solicitud de Materiales
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Formular nuevas solicitudes de equipos o insumos y revisar estados de atención en tiempo real.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                Ir a solicitudes &rarr;
                            </span>
                        </a>

                        <!-- Módulo Docente: Informe de Novedades -->
                        <a href="{{ route('docente.novedades') }}" class="group block p-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-amber-300 transition" wire:navigate>
                            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-slate-900 group-hover:text-amber-700 transition">
                                Informe de Novedades
                            </h3>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Reportar novedades técnicas en instrumental o consumibles, consultar estado de atención y descargar reportes PDF.
                            </p>
                            <span class="mt-4 inline-flex items-center text-xs font-semibold text-amber-700 group-hover:translate-x-1 transition">
                                Reportar novedad &rarr;
                            </span>
                        </a>
                    </div>
                </div>
            @else
                @php
                    // Consultas optimizadas para Sucesos Importantes
                    $insumosCriticos = \App\Models\Insumo::with(['ubicacion.carrera'])
                        ->whereRaw('stock_actual <= stock_minimo')
                        ->orderBy('stock_actual')
                        ->take(3)
                        ->get();
                    $totalCriticos = \App\Models\Insumo::whereRaw('stock_actual <= stock_minimo')->count();

                    $ahora = now();
                    $hoy = $ahora->toDateString();
                    $horaActual = $ahora->format('H:i:s');

                    $prestamosVencidosQuery = \App\Models\Entrega::with(['solicitud.solicitante', 'solicitud.detalles.equipo', 'solicitud.detalles.insumo'])
                        ->whereHas('solicitud', fn($q) => $q->where('estado', 'entregada_abierta'))
                        ->where(function ($q) use ($hoy, $horaActual) {
                            $q->whereDate('fecha_entrega', '<', $hoy)
                              ->orWhere(function ($q2) use ($hoy, $horaActual) {
                                  $q2->whereDate('fecha_entrega', $hoy)
                                     ->whereNotNull('hora_devolucion_acordada')
                                     ->where('hora_devolucion_acordada', '<', $horaActual);
                              });
                        });
                    $totalPrestamosVencidos = (clone $prestamosVencidosQuery)->count();
                    $prestamosVencidos = $prestamosVencidosQuery->latest('id')->take(2)->get();

                    $fallasPendientesCount = \App\Models\Falla::whereIn('estado', ['en_revision', 'diagnosticado', 'pendiente'])->count();

                    $mantenimientosProximosCount = \App\Models\Mantenimiento::where('estado', 'programado')
                        ->whereDate('fecha_programada', '<=', now()->addDays(7))
                        ->count();

                    $solicitudesDespachoCount = \App\Models\SolicitudMaterial::whereIn('estado', ['pendiente', 'aprobada'])
                        ->whereDate('fecha_requerida', '<=', now()->toDateString())
                        ->count();

                    $totalAlertas = $totalCriticos + $totalPrestamosVencidos + $fallasPendientesCount + $mantenimientosProximosCount;
                @endphp

                <!-- Layout Principal: Módulos a la izquierda, Sucesos Importantes a la derecha -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    
                    <!-- Columna Izquierda: Módulos Operativos (7 columnas) -->
                    <div class="lg:col-span-7 space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                Módulos
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Despacho y Devoluciones -->
                            <a href="{{ route('panol.despacho') }}" class="group block p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-red-300 transition" wire:navigate>
                                <div class="w-10 h-10 rounded-xl bg-red-50 text-red-700 flex items-center justify-center group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                </div>
                                <h3 class="mt-3 text-sm font-bold text-slate-900 group-hover:text-red-700 transition">
                                    Despacho y Devoluciones
                                </h3>
                                <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                                    Escaneo con código de barras, entrega de materiales a docentes/alumnos y recepción.
                                </p>
                                <span class="mt-3 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                    Gestionar entregas &rarr;
                                </span>
                            </a>

                            <!-- Inventario de Consumibles -->
                            <a href="{{ route('panol.inventario') }}" class="group block p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-red-300 transition" wire:navigate>
                                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <h3 class="mt-3 text-sm font-bold text-slate-900 group-hover:text-red-700 transition">
                                    Inventario de Consumibles
                                </h3>
                                <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                                    Control de existencias de insumos, monitoreo de stock crítico y ajustes físicos.
                                </p>
                                <span class="mt-3 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                    Ver inventario &rarr;
                                </span>
                            </a>

                            <!-- Fichas y Trazabilidad de Equipos -->
                            <a href="{{ route('panol.equipos') }}" class="group block p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-red-300 transition" wire:navigate>
                                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 class="mt-3 text-sm font-bold text-slate-900 group-hover:text-red-700 transition">
                                    Fichas y Equipos
                                </h3>
                                <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                                    Catálogo individualizado con números de serie, marcas, modelos y fotografías.
                                </p>
                                <span class="mt-3 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                    Administrar equipos &rarr;
                                </span>
                            </a>

                            <!-- Mantenimientos y Diagnósticos -->
                            <a href="{{ route('panol.mantenimientos') }}" class="group block p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-red-300 transition" wire:navigate>
                                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <h3 class="mt-3 text-sm font-bold text-slate-900 group-hover:text-red-700 transition">
                                    Mantenimientos
                                </h3>
                                <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                                    Planificación preventiva, equipos con fallas reportadas y diagnósticos técnicos.
                                </p>
                                <span class="mt-3 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                    Gestionar fallas &rarr;
                                </span>
                            </a>

                            <!-- Ingreso por Factura -->
                            <a href="{{ route('panol.compras') }}" class="group block p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-red-300 transition" wire:navigate>
                                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <h3 class="mt-3 text-sm font-bold text-slate-900 group-hover:text-red-700 transition">
                                    Ingreso por Factura
                                </h3>
                                <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                                    Recepción y registro de compras con deducción presupuestaria.
                                </p>
                                <span class="mt-3 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                    Registrar compras &rarr;
                                </span>
                            </a>

                            <!-- Impresión de Etiquetas -->
                            <a href="{{ route('panol.etiquetas') }}" class="group block p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-red-300 transition" wire:navigate>
                                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                </div>
                                <h3 class="mt-3 text-sm font-bold text-slate-900 group-hover:text-red-700 transition">
                                    Etiquetas
                                </h3>
                                <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                                    Generación e impresión física de códigos de barras lineales Code128.
                                </p>
                                <span class="mt-3 inline-flex items-center text-xs font-semibold text-red-700 group-hover:translate-x-1 transition">
                                    Generar etiquetas &rarr;
                                </span>
                            </a>
                        </div>
                    </div>

                    <!-- Columna Derecha: Sucesos Importantes (5 columnas) -->
                    <div class="lg:col-span-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                Sucesos Importantes
                            </h2>
                            @if($totalAlertas > 0)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                                    {{ $totalAlertas }} pendientes
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    Al día
                                </span>
                            @endif
                        </div>

                        <!-- 1. Alerta de Stock Crítico -->
                        @if($totalCriticos > 0)
                            <div class="bg-red-50/90 border border-red-200 rounded-2xl p-4 shadow-xs space-y-3">
                                <div class="flex items-center justify-between gap-2 border-b border-red-200/70 pb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-red-600 text-white flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        </div>
                                        <div>
                                            <div class="font-bold text-xs text-red-950">Stock Crítico ({{ $totalCriticos }})</div>
                                            <div class="text-[11px] text-red-800">Insumos en o bajo umbral mínimo</div>
                                        </div>
                                    </div>
                                    <a href="{{ route('panol.inventario') }}" class="text-[11px] font-bold text-red-700 hover:text-red-900" wire:navigate>
                                        Ver todos &rarr;
                                    </a>
                                </div>

                                <div class="space-y-2">
                                    @foreach($insumosCriticos as $critico)
                                        <div class="bg-white rounded-xl border border-red-200 p-3 flex flex-col justify-between shadow-2xs">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <span class="font-mono text-[10px] font-bold text-slate-500">{{ $critico->codigo_barras }}</span>
                                                    <h4 class="font-bold text-slate-900 text-xs leading-tight truncate">{{ $critico->nombre }}</h4>
                                                    <div class="text-[10px] text-slate-500 mt-0.5 truncate">
                                                        {{ $critico->ubicacion?->nombre ?? 'Sin ubicación' }}
                                                    </div>
                                                </div>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-red-100 text-red-800 shrink-0">
                                                    Crítico
                                                </span>
                                            </div>
                                            <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                                                <div class="text-[11px]">
                                                    <span class="text-red-700 font-extrabold">Stock: {{ (float)$critico->stock_actual }} {{ $critico->unidad }}</span>
                                                    <span class="text-slate-400">/ Mín: {{ (float)$critico->stock_minimo }}</span>
                                                </div>
                                                <a
                                                    href="{{ route('panol.cotizaciones', ['insumo_id' => $critico->id]) }}"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-700 hover:bg-red-800 text-white rounded-md text-[11px] font-bold shadow-2xs transition"
                                                    wire:navigate
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                    Cotizar
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- 2. Alerta de Préstamos Vencidos -->
                        @if($totalPrestamosVencidos > 0)
                            <div class="bg-rose-50/90 border border-rose-200 rounded-2xl p-4 shadow-xs space-y-3">
                                <div class="flex items-center justify-between gap-2 border-b border-rose-200/70 pb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-rose-600 text-white flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <div>
                                            <div class="font-bold text-xs text-rose-950">Préstamos Vencidos</div>
                                            <div class="text-[11px] text-rose-800">{{ $totalPrestamosVencidos }} por devolver</div>
                                        </div>
                                    </div>
                                    <a href="{{ route('panol.devoluciones') }}" class="text-[11px] font-bold text-rose-700 hover:text-rose-900" wire:navigate>
                                        Ver devoluciones &rarr;
                                    </a>
                                </div>

                                <div class="space-y-2">
                                    @foreach($prestamosVencidos as $vencido)
                                        <div class="bg-white rounded-xl border border-rose-200 p-3 flex flex-col justify-between shadow-2xs">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <span class="font-bold text-slate-900 text-xs truncate block">{{ $vencido->receptor_nombre }}</span>
                                                    <span class="text-[10px] text-slate-500">({{ ucfirst($vencido->receptor_tipo) }})</span>
                                                    <div class="text-[10px] text-rose-700 font-semibold mt-0.5">
                                                        Límite: {{ $vencido->hora_devolucion_acordada ? \Carbon\Carbon::parse($vencido->hora_devolucion_acordada)->format('H:i') . ' hrs' : $vencido->fecha_entrega->format('d/m/Y') }}
                                                    </div>
                                                </div>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 shrink-0">
                                                    Atrasado
                                                </span>
                                            </div>
                                            <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-end">
                                                <a
                                                    href="{{ route('panol.devoluciones') }}"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-rose-700 hover:bg-rose-800 text-white rounded-md text-[11px] font-bold shadow-2xs transition"
                                                    wire:navigate
                                                >
                                                    Devolver
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- 3. Alertas Secundarias: Fallas, Mantenimientos y Solicitudes -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-2.5">
                            <!-- Fallas Técnicas -->
                            <a href="{{ route('panol.mantenimientos') }}" class="p-3 bg-white rounded-xl border border-slate-200 shadow-2xs hover:border-slate-300 transition flex items-center justify-between" wire:navigate>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg {{ $fallasPendientesCount > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Fallas Técnicas</div>
                                        <div class="text-[11px] text-slate-500">
                                            {{ $fallasPendientesCount > 0 ? "{$fallasPendientesCount} equipos con avería" : 'Sin fallas pendientes' }}
                                        </div>
                                    </div>
                                </div>
                                <span class="text-xs font-bold text-slate-400">&rarr;</span>
                            </a>

                            <!-- Mantenimientos Preventivos -->
                            <a href="{{ route('panol.mantenimientos') }}" class="p-3 bg-white rounded-xl border border-slate-200 shadow-2xs hover:border-slate-300 transition flex items-center justify-between" wire:navigate>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg {{ $mantenimientosProximosCount > 0 ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Mantenimientos Próximos</div>
                                        <div class="text-[11px] text-slate-500">
                                            {{ $mantenimientosProximosCount > 0 ? "{$mantenimientosProximosCount} agendados esta semana" : 'Sin mantenimientos urgentes' }}
                                        </div>
                                    </div>
                                </div>
                                <span class="text-xs font-bold text-slate-400">&rarr;</span>
                            </a>

                            <!-- Solicitudes por Despachar -->
                            <a href="{{ route('panol.despacho') }}" class="p-3 bg-white rounded-xl border border-slate-200 shadow-2xs hover:border-slate-300 transition flex items-center justify-between" wire:navigate>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg {{ $solicitudesDespachoCount > 0 ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Solicitudes por Entregar</div>
                                        <div class="text-[11px] text-slate-500">
                                            {{ $solicitudesDespachoCount > 0 ? "{$solicitudesDespachoCount} pedidos para hoy" : 'Sin pedidos pendientes' }}
                                        </div>
                                    </div>
                                </div>
                                <span class="text-xs font-bold text-slate-400">&rarr;</span>
                            </a>
                        </div>

                        @if($totalAlertas === 0)
                            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-center text-xs text-emerald-800">
                                <svg class="w-6 h-6 text-emerald-600 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="font-bold">¡Operación al día!</span>
                                <p class="text-[11px] text-emerald-700 mt-0.5">No hay alertas de stock ni préstamos con retraso.</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
