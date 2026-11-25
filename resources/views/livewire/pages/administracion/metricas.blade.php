<?php

use App\Services\DashboardMetricasService;
use App\Services\FormatoHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(as: 'periodo')]
    public string $periodo = '30_dias';

    #[Url(as: 'tab')]
    public string $tabActiva = 'demanda';

    public string $materialTab = 'equipos';

    public function mount(): void
    {
        if (Auth::user()?->rol !== 'administrador') {
            abort(403, 'Acceso restringido exclusivamente a Administradores.');
        }

        if (! in_array($this->periodo, ['30_dias', 'semestre', 'anio', 'historico'], true)) {
            $this->periodo = '30_dias';
        }

        if (! in_array($this->tabActiva, ['demanda', 'confiabilidad', 'presupuesto'], true)) {
            $this->tabActiva = 'demanda';
        }
    }

    public function seleccionarPeriodo(string $periodo): void
    {
        if (in_array($periodo, ['30_dias', 'semestre', 'anio', 'historico'], true)) {
            $this->periodo = $periodo;
        }
    }

    public function seleccionarTab(string $tab): void
    {
        if (in_array($tab, ['demanda', 'confiabilidad', 'presupuesto'], true)) {
            $this->tabActiva = $tab;
        }
    }

    public function with(DashboardMetricasService $metricasService): array
    {
        return [
            'puntualidad' => $metricasService->obtenerMetricasPuntualidadEstudiantes($this->periodo),
            'resumenGeneral' => $metricasService->obtenerResumenGeneral($this->periodo),
            'distribucionHoraria' => $metricasService->obtenerDistribucionHoraria($this->periodo),
            'topMateriales' => $metricasService->obtenerTopMateriales(5, $this->periodo),
            'topFallas' => $metricasService->obtenerTopEquiposConFallas(8, $this->periodo),
            'resumenMantenimientos' => $metricasService->obtenerResumenMantenimientosYBajas($this->periodo),
            'resumenPresupuesto' => $metricasService->obtenerMetricasPresupuestosYCompras($this->periodo),
        ];
    }
}; ?>

<div class="py-6 sm:py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- Cabecera y Selector Interactivo de Período -->
        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-600 animate-pulse"></span>
                        <h1 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">
                            Panel de Métricas y Trazabilidad Operativa
                        </h1>
                        <span wire:loading class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700 border border-red-200">
                            Actualizando...
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Indicadores estratégicos interactivos sobre préstamos, puntualidad estudiantil, fallas de instrumental y ejecución presupuestaria.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Selector de Período Interactivo -->
                    <div class="inline-flex flex-wrap rounded-xl p-1 bg-slate-100 border border-slate-200 text-xs font-semibold" role="group" aria-label="Filtro de período de métricas">
                        @foreach ([
                            '30_dias' => 'Últimos 30 días',
                            'semestre' => 'Último Semestre',
                            'anio' => 'Último Año',
                            'historico' => 'Histórico Completo',
                        ] as $clave => $etiqueta)
                            <button
                                type="button"
                                wire:click="seleccionarPeriodo('{{ $clave }}')"
                                class="px-3 py-1.5 rounded-lg transition focus:outline-none focus:ring-2 focus:ring-red-600 {{ $periodo === $clave ? 'bg-white text-red-700 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' }}"
                            >
                                {{ $etiqueta }}
                            </button>
                        @endforeach
                    </div>

                    <a
                        href="{{ route('dashboard') }}"
                        wire:navigate
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-red-600"
                    >
                        <span>&larr;</span>
                        <span>Volver a Inicio</span>
                    </a>
                </div>
            </div>

            <!-- Barra de Sub-pestañas Temáticas Interactivas -->
            <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100" role="tablist" aria-label="Categorías de métricas">
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tabActiva === 'demanda' ? 'true' : 'false' }}"
                    wire:click="seleccionarTab('demanda')"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition focus:outline-none focus:ring-2 focus:ring-red-600 {{ $tabActiva === 'demanda' ? 'bg-red-700 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200/70' }}"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Operación y Demanda</span>
                </button>

                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tabActiva === 'confiabilidad' ? 'true' : 'false' }}"
                    wire:click="seleccionarTab('confiabilidad')"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition focus:outline-none focus:ring-2 focus:ring-red-600 {{ $tabActiva === 'confiabilidad' ? 'bg-red-700 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200/70' }}"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Confiabilidad y Fallas</span>
                </button>

                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tabActiva === 'presupuesto' ? 'true' : 'false' }}"
                    wire:click="seleccionarTab('presupuesto')"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition focus:outline-none focus:ring-2 focus:ring-red-600 {{ $tabActiva === 'presupuesto' ? 'bg-red-700 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200/70' }}"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Presupuesto y Compras</span>
                </button>
            </div>
        </div>

        <!-- 4 Tarjetas KPI Principales -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" wire:loading.class="opacity-60 transition-opacity">
            <!-- KPI 1: % Puntualidad Estudiantil -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Puntualidad Estudiantes</span>
                    <div class="w-8 h-8 rounded-lg {{ $puntualidad['porcentaje_a_tiempo'] >= 90 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold tracking-tight {{ $puntualidad['porcentaje_a_tiempo'] >= 90 ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ number_format($puntualidad['porcentaje_a_tiempo'], 1) }}%
                        </span>
                        <span class="text-xs font-semibold text-slate-500">a tiempo</span>
                    </div>
                    <div class="mt-2 text-xs text-slate-600 flex justify-between border-t border-slate-100 pt-2">
                        <span>{{ $puntualidad['a_tiempo'] }} a la hora</span>
                        <span class="text-rose-600 font-semibold">{{ $puntualidad['con_retraso'] }} atrasadas</span>
                    </div>
                </div>
            </div>

            <!-- KPI 2: Préstamos en Ventanilla -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Préstamos en Ventanilla</span>
                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-extrabold tracking-tight text-slate-900">
                        {{ number_format($resumenGeneral['total_prestamos'], 0, ',', '.') }}
                    </div>
                    <div class="mt-2 text-xs text-slate-500 flex items-center justify-between border-t border-slate-100 pt-2">
                        <span>Atenciones en pañol</span>
                        <a href="{{ route('panol.despacho') }}" wire:navigate class="font-semibold text-blue-700 hover:text-blue-900">Despacho &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- KPI 3: Fallas / Averías -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Fallas / Averías</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-extrabold tracking-tight text-slate-900">
                        {{ number_format($resumenGeneral['total_fallas'], 0, ',', '.') }}
                    </div>
                    <div class="mt-2 text-xs text-slate-500 flex items-center justify-between border-t border-slate-100 pt-2">
                        <span>Reportes técnicos</span>
                        <a href="{{ route('panol.mantenimientos') }}" wire:navigate class="font-semibold text-amber-700 hover:text-amber-900">Revisar &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- KPI 4: Mora Activa en Pañol -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Mora Activa en Pañol</span>
                    <div class="w-8 h-8 rounded-lg {{ $puntualidad['retrasos_activos'] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-50 text-emerald-700' }} flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-extrabold tracking-tight {{ $puntualidad['retrasos_activos'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                        {{ $puntualidad['retrasos_activos'] }}
                    </div>
                    <div class="mt-2 text-xs text-slate-500 flex items-center justify-between border-t border-slate-100 pt-2">
                        <span class="truncate">{{ $puntualidad['retrasos_activos'] > 0 ? 'Con hora cumplida' : 'Sin mora actualmente' }}</span>
                        <a href="{{ route('panol.devoluciones') }}" wire:navigate class="font-semibold text-rose-700 hover:text-rose-900 shrink-0">Devoluciones &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido Según Pestaña Seleccionada -->
        @if($tabActiva === 'demanda')
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Gráfico de Demanda Horaria (7 columnas) -->
                <div class="lg:col-span-7 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                ¿En qué horas se piden más cosas? Distribución de Demanda en Ventanilla
                            </h2>
                            <p class="text-xs text-slate-500">
                                Salidas de materiales y atenciones horarias entre 08:00 y 21:00 hrs. ({{ $distribucionHoraria['total_entregas'] }} registradas).
                            </p>
                        </div>
                        <div class="flex items-center gap-4 text-xs shrink-0">
                            <span class="inline-flex items-center gap-1.5 text-slate-600">
                                <span class="w-3 h-3 rounded-sm bg-slate-200"></span> Normal
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-red-700 font-semibold">
                                <span class="w-3 h-3 rounded-sm bg-red-600"></span> Horas Punta
                            </span>
                        </div>
                    </div>

                    <div class="pt-6 pb-2">
                        <div class="grid grid-cols-7 sm:grid-cols-14 gap-2 items-end h-52">
                            @foreach($distribucionHoraria['horas'] as $item)
                                <div class="flex flex-col items-center h-full justify-end group" title="{{ $item['hora'] }} hrs: {{ $item['total'] }} atenciones ({{ $item['porcentaje'] }}% del peak)">
                                    <span class="text-[11px] font-bold text-slate-600 group-hover:text-red-700 transition mb-1 {{ $item['es_punta'] ? 'text-red-700 font-extrabold' : '' }}">
                                        {{ $item['total'] }}
                                    </span>
                                    <div class="w-full rounded-t-lg transition-all duration-300 {{ $item['es_punta'] ? 'bg-red-600 group-hover:bg-red-700 shadow-sm' : 'bg-slate-200 group-hover:bg-slate-300' }}"
                                         style="height: {{ max(6, $item['porcentaje']) }}%; min-height: 8px;">
                                    </div>
                                    <span class="text-[10px] font-semibold text-slate-500 mt-2 truncate">
                                        {{ $item['hora_num'] }}:00
                                    </span>
                                    @if($item['es_punta'])
                                        <span class="text-[9px] font-bold text-red-700 uppercase tracking-tighter mt-0.5">
                                            Peak
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Materiales Más Solicitados (5 columnas) -->
                <div class="lg:col-span-5 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Materiales Más Solicitados</h2>
                                <p class="text-xs text-slate-500">Demanda de instrumental y rotación de consumibles.</p>
                            </div>
                            <div class="inline-flex rounded-lg p-0.5 bg-slate-100 text-[11px] font-semibold shrink-0">
                                <button
                                    type="button"
                                    wire:click="$set('materialTab', 'equipos')"
                                    class="px-2.5 py-1 rounded-md transition {{ $materialTab === 'equipos' ? 'bg-white text-red-700 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900' }}"
                                >
                                    Equipos
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('materialTab', 'insumos')"
                                    class="px-2.5 py-1 rounded-md transition {{ $materialTab === 'insumos' ? 'bg-white text-red-700 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900' }}"
                                >
                                    Insumos
                                </button>
                            </div>
                        </div>

                        @if($materialTab === 'equipos')
                            <div class="mt-4 divide-y divide-slate-100">
                                @forelse($topMateriales['equipos'] as $eqItem)
                                    <div class="py-3 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-bold text-xs text-slate-900 truncate">
                                                {{ $eqItem->nombre }}
                                            </div>
                                            <div class="text-[11px] text-slate-500 mt-0.5">
                                                {{ $eqItem->marca }} {{ $eqItem->modelo }}
                                            </div>
                                        </div>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-800 shrink-0">
                                            {{ $eqItem->veces_pedido }} {{ $eqItem->veces_pedido == 1 ? 'préstamo' : 'préstamos' }}
                                        </span>
                                    </div>
                                @empty
                                    <div class="py-8 text-center text-xs text-slate-400">
                                        No hay solicitudes de equipos en este período.
                                    </div>
                                @endforelse
                            </div>
                        @else
                            <div class="mt-4 divide-y divide-slate-100">
                                @forelse($topMateriales['insumos'] as $insItem)
                                    <div class="py-3 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-bold text-xs text-slate-900 truncate">
                                                {{ $insItem->nombre }}
                                            </div>
                                            <div class="text-[11px] text-slate-500 mt-0.5">
                                                Consumo acumulado ({{ number_format($resumenGeneral['total_insumos_consumidos'], 0, ',', '.') }} total período)
                                            </div>
                                        </div>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 shrink-0">
                                            {{ (float) $insItem->total_entregado }} {{ $insItem->unidad }}
                                        </span>
                                    </div>
                                @empty
                                    <div class="py-8 text-center text-xs text-slate-400">
                                        No hay salidas de insumos en este período.
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <a href="{{ route('panol.equipos') }}" wire:navigate class="font-semibold text-slate-600 hover:text-red-700">
                            Catálogo de equipos &rarr;
                        </a>
                        <a href="{{ route('panol.inventario') }}" wire:navigate class="font-semibold text-slate-600 hover:text-red-700">
                            Ver inventario general &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @elseif($tabActiva === 'confiabilidad')
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Ranking Equipos que Más Fallan (7 columnas) -->
                <div class="lg:col-span-7 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900">Equipos que Más Fallan</h2>
                                    <p class="text-xs text-slate-500">Activos con mayor historial de averías reportadas en el período.</p>
                                </div>
                            </div>
                            <a href="{{ route('panol.mantenimientos') }}" class="text-xs font-bold text-red-700 hover:text-red-900" wire:navigate>
                                Mantenimientos &rarr;
                            </a>
                        </div>

                        <div class="mt-4 divide-y divide-slate-100">
                            @forelse($topFallas as $fallaItem)
                                <div class="py-3 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-bold text-xs text-slate-900 truncate">
                                            {{ $fallaItem->equipo?->nombre ?? 'Equipo no identificado' }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            <span>{{ $fallaItem->equipo?->marca }} {{ $fallaItem->equipo?->modelo }}</span>
                                            <span class="mx-1">&bull;</span>
                                            <span class="font-mono text-[10px]">{{ $fallaItem->equipo?->codigo_barras }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-amber-100 text-amber-800 border border-amber-200">
                                            {{ $fallaItem->total_fallas }} {{ $fallaItem->total_fallas == 1 ? 'falla' : 'fallas' }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="py-10 text-center text-xs text-slate-400">
                                    No hay fallas técnicas registradas en este período.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-right">
                        <a href="{{ route('admin.bajas') }}" class="text-xs font-semibold text-slate-600 hover:text-red-700" wire:navigate>
                            Consultar expediente de bajas técnicas &rarr;
                        </a>
                    </div>
                </div>

                <!-- Resumen Mantenimientos y Bajas (5 columnas) -->
                <div class="lg:col-span-5 space-y-4">
                    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                        <div class="border-b border-slate-100 pb-3">
                            <h2 class="text-sm font-bold text-slate-900">Estado de Mantenimientos</h2>
                            <p class="text-xs text-slate-500">Distribución de intervenciones técnicas programadas.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-100">
                                <div class="text-[11px] font-bold uppercase text-blue-800">Preventivos</div>
                                <div class="text-2xl font-extrabold text-blue-900 mt-1">{{ $resumenMantenimientos['mantenimientos_preventivos'] }}</div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-amber-50/70 border border-amber-100">
                                <div class="text-[11px] font-bold uppercase text-amber-800">Correctivos</div>
                                <div class="text-2xl font-extrabold text-amber-900 mt-1">{{ $resumenMantenimientos['mantenimientos_correctivos'] }}</div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-100">
                                <div class="text-[11px] font-bold uppercase text-emerald-800">Realizados</div>
                                <div class="text-2xl font-extrabold text-emerald-900 mt-1">{{ $resumenMantenimientos['mantenimientos_realizados'] }}</div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="text-[11px] font-bold uppercase text-slate-600">Pendientes</div>
                                <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $resumenMantenimientos['mantenimientos_pendientes'] }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Salud de Flota y Bajas</h2>
                                <p class="text-xs text-slate-500">Disponibilidad actual y resoluciones técnicas.</p>
                            </div>
                            <a href="{{ route('admin.bajas') }}" wire:navigate class="text-xs font-bold text-red-700 hover:text-red-900">
                                Bajas &rarr;
                            </a>
                        </div>

                        <div class="space-y-2.5 text-xs">
                            <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
                                <span class="text-slate-600">Equipos operativos (disponibles / prestados)</span>
                                <span class="font-extrabold text-emerald-700">{{ $resumenMantenimientos['equipos_activos'] }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
                                <span class="text-slate-600">Equipos actualmente en mantenimiento</span>
                                <span class="font-extrabold text-amber-700">{{ $resumenMantenimientos['equipos_en_mantenimiento'] }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
                                <span class="text-slate-600">Solicitudes de baja propuestas en período</span>
                                <span class="font-extrabold text-rose-700">{{ $resumenMantenimientos['bajas_propuestas'] }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1.5">
                                <span class="text-slate-600">Bajas / reemplazos aprobados en período</span>
                                <span class="font-extrabold text-slate-900">{{ $resumenMantenimientos['bajas_aprobadas'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Tab 3: Presupuesto y Compras -->
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Presupuesto Asignado ({{ $resumenPresupuesto['anio'] }})</span>
                        <div class="text-2xl font-extrabold text-slate-900 mt-2">
                            {{ FormatoHelper::formatearMoneda($resumenPresupuesto['total_asignado']) }}
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Suma anual asignada entre carreras</p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Saldo Disponible Vigente</span>
                        <div class="text-2xl font-extrabold text-emerald-700 mt-2">
                            {{ FormatoHelper::formatearMoneda($resumenPresupuesto['total_saldo']) }}
                        </div>
                        <p class="text-xs text-slate-500 mt-1">{{ number_format($resumenPresupuesto['porcentaje_ejecutado'], 1) }}% del presupuesto ejecutado</p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Compras Registradas (Período)</span>
                        <div class="text-2xl font-extrabold text-red-700 mt-2">
                            {{ FormatoHelper::formatearMoneda($resumenPresupuesto['monto_compras_periodo']) }}
                        </div>
                        <p class="text-xs text-slate-500 mt-1">{{ $resumenPresupuesto['total_compras_periodo'] }} documentos de compra válidos</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Ejecución Presupuestaria por Carrera ({{ $resumenPresupuesto['anio'] }})</h2>
                            <p class="text-xs text-slate-500">Control de saldo disponible y porcentaje consumido por especialidad.</p>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <a href="{{ route('admin.carreras') }}" wire:navigate class="font-bold text-red-700 hover:text-red-900">Carreras y Presupuesto &rarr;</a>
                            <a href="{{ route('admin.compras') }}" wire:navigate class="font-semibold text-slate-600 hover:text-red-700">Compras &rarr;</a>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @forelse($resumenPresupuesto['carreras'] as $carreraItem)
                            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-2">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                    <div>
                                        <span class="font-mono text-[11px] font-bold text-slate-500">{{ $carreraItem['carrera_codigo'] }}</span>
                                        <span class="font-bold text-xs sm:text-sm text-slate-900 ml-1">{{ $carreraItem['carrera_nombre'] }}</span>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-700">
                                        Saldo: <span class="text-emerald-700 font-bold">{{ FormatoHelper::formatearMoneda($carreraItem['saldo_actual']) }}</span>
                                        <span class="text-slate-400 mx-1">/</span>
                                        Asignado: {{ FormatoHelper::formatearMoneda($carreraItem['monto_asignado']) }}
                                    </div>
                                </div>
                                <div class="w-full h-2.5 bg-slate-200 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-300 {{ $carreraItem['porcentaje_ejecutado'] >= 85 ? 'bg-red-600' : ($carreraItem['porcentaje_ejecutado'] >= 60 ? 'bg-amber-500' : 'bg-emerald-600') }}"
                                        style="width: {{ $carreraItem['porcentaje_ejecutado'] }}%;"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-500">
                                    <span>Ejecutado: {{ FormatoHelper::formatearMoneda($carreraItem['monto_ejecutado']) }}</span>
                                    <span class="font-bold">{{ number_format($carreraItem['porcentaje_ejecutado'], 1) }}% consumido</span>
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center text-xs text-slate-400">
                                No hay presupuestos configurados para el año {{ $resumenPresupuesto['anio'] }}.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
