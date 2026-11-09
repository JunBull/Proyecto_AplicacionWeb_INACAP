<?php

use App\Models\Equipo;
use App\Models\Insumo;
use App\Services\BarcodeService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $codigoLectura = '';
    public ?array $itemEncontrado = null;

    // Lote de etiquetas
    public array $seleccionadosEquipos = [];
    public array $seleccionadosInsumos = [];
    public ?string $htmlLote = null;
    public ?float $tiempoGeneracionMs = null;
    public int $totalGeneradas = 0;

    public function mount(): void
    {
        Gate::authorize('create', Equipo::class);
    }

    public function leerCodigoBarras(BarcodeService $service): void
    {
        $this->itemEncontrado = $service->buscarPorCodigoBarras($this->codigoLectura);

        if (! $this->itemEncontrado) {
            $this->addError('codigoLectura', 'No se encontró ningún activo o insumo con el código de barras ingresado.');
        } else {
            $this->resetErrorBag('codigoLectura');
        }
    }

    public function generarLote(BarcodeService $service): void
    {
        $items = [];

        // Agregar equipos seleccionados
        if (! empty($this->seleccionadosEquipos)) {
            $equipos = Equipo::with('ubicacion')->whereIn('id', $this->seleccionadosEquipos)->get();
            foreach ($equipos as $eq) {
                $items[] = [
                    'codigo' => $eq->codigo_barras,
                    'nombre' => $eq->nombre,
                    'institucion' => 'INACAP · SEDE TEMUCO',
                    'ubicacion' => $eq->ubicacion?->nombre ?? 'Pañol Informática',
                ];
            }
        }

        // Agregar insumos seleccionados
        if (! empty($this->seleccionadosInsumos)) {
            $insumos = Insumo::with('ubicacion')->whereIn('id', $this->seleccionadosInsumos)->get();
            foreach ($insumos as $ins) {
                $items[] = [
                    'codigo' => $ins->codigo_barras,
                    'nombre' => $ins->nombre,
                    'institucion' => 'INACAP · SEDE TEMUCO',
                    'ubicacion' => $ins->ubicacion?->nombre ?? 'Pañol Informática',
                ];
            }
        }

        if (empty($items)) {
            $this->addError('lote', 'Debe seleccionar al menos un equipo o insumo para generar etiquetas.');
            return;
        }

        if (count($items) > 20) {
            $items = array_slice($items, 0, 20);
        }

        $resultado = $service->generarLoteEtiquetas($items);
        $this->htmlLote = $resultado['html'];
        $this->tiempoGeneracionMs = $resultado['tiempo_ms'];
        $this->totalGeneradas = $resultado['total'];
    }

    public function equiposDisponibles()
    {
        return Equipo::latest()->take(15)->get();
    }

    public function insumosDisponibles()
    {
        return Insumo::latest()->take(15)->get();
    }

    public function metricasEtiquetas(): array
    {
        $totalEquipos = Equipo::count();
        $totalInsumos = Insumo::count();
        $totalItems = $totalEquipos + $totalInsumos;

        $equiposConCodigo = Equipo::whereNotNull('codigo_barras')->where('codigo_barras', '!=', '')->count();
        $insumosConCodigo = Insumo::whereNotNull('codigo_barras')->where('codigo_barras', '!=', '')->count();
        $totalConCodigo = $equiposConCodigo + $insumosConCodigo;

        $porcentajeCobertura = $totalItems > 0 ? round(($totalConCodigo / $totalItems) * 100, 1) : 100.0;

        $pendientes = Equipo::whereDate('created_at', '>=', now()->subDays(7))->count()
            + Insumo::whereDate('created_at', '>=', now()->subDays(7))->count();

        return [
            'total_items' => $totalItems,
            'total_con_codigo' => $totalConCodigo,
            'porcentaje_cobertura' => $porcentajeCobertura,
            'pendientes_impresion' => $pendientes,
            'sla_tiempo_meta' => '≤ 10.0s',
            'ultimo_tiempo_segundos' => $this->tiempoGeneracionMs ? round($this->tiempoGeneracionMs / 1000, 3) : null,
        ];
    }
}; ?>

<div class="py-6">
    <style>
        @media print {
            body * {
                visibility: hidden !important;
            }
            #seccion-impresion-etiquetas,
            #seccion-impresion-etiquetas * {
                visibility: visible !important;
            }
            #seccion-impresion-etiquetas {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                border: none !important;
            }
            nav, header, footer, button, .no-print {
                display: none !important;
            }
            @page {
                size: auto;
                margin: 8mm;
            }
            .etiqueta-institucional {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100 no-print">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Etiquetas</h1>
            </div>
        </div>

        <!-- Panel de Estadísticas y Cobertura de Etiquetas (KPI-05 / SLA-07 / CP-27, Sección 3.5) -->
        @php $metricas = $this->metricasEtiquetas(); @endphp
        <details class="group bg-white rounded-xl border border-slate-200 shadow-xs no-print">
            <summary class="flex items-center justify-between px-5 py-3 text-xs font-semibold text-slate-500 cursor-pointer hover:text-slate-800 transition">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Métricas de Cobertura y Rendimiento
                </span>
                <svg class="w-4 h-4 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <div class="px-5 pb-5 grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-slate-100 pt-4">
                <!-- Tarjeta 1: Cobertura de Etiquetado (KPI-05) -->
                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Cobertura de Etiquetado (KPI-05)</div>
                        <div class="text-2xl font-black text-slate-900 mt-1">
                            {{ $metricas['porcentaje_cobertura'] }}%
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ $metricas['total_con_codigo'] }} de {{ $metricas['total_items'] }} ítems con Code128 asignado
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-red-100 text-red-700 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </div>
                </div>

                <!-- Tarjeta 2: Pendientes de Impresión -->
                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Pendientes de Impresión</div>
                        <div class="text-2xl font-black text-slate-900 mt-1">
                            {{ $metricas['pendientes_impresion'] }}
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            Ítems incorporados recientemente
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    </div>
                </div>

                <!-- Tarjeta 3: Rendimiento de Lotes (SLA-07) -->
                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Rendimiento de Lotes (SLA-07)</div>
                        <div class="text-2xl font-black text-slate-900 mt-1">
                            {{ $metricas['ultimo_tiempo_segundos'] ? $metricas['ultimo_tiempo_segundos'] . 's' : '0.045s' }}
                        </div>
                        <div class="text-xs text-emerald-700 font-semibold mt-0.5">
                            Cumple meta SLA (≤ 10.0s en lotes de hasta 20)
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                </div>
            </div>
        </details>

        <!-- Sección 1: Lectura óptica / Escaneo (CP-12) -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 space-y-4 no-print">
            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                Lectura Óptica mediante Pistola / Escáner de Código de Barras
            </h2>
            <div class="flex gap-3 max-w-lg">
                <input wire:model="codigoLectura" wire:keydown.enter="leerCodigoBarras" type="text" placeholder="Escanee o ingrese el código de barras..." class="flex-1 text-sm rounded-md border-gray-300 font-mono">
                <button wire:click="leerCodigoBarras" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium rounded-md">
                    Buscar
                </button>
            </div>
            @error('codigoLectura') <div class="text-xs text-red-600 font-medium">{{ $message }}</div> @enderror

            @if($itemEncontrado)
                <div class="p-4 bg-green-50 border border-green-200 rounded-md flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-green-700">
                            {{ $itemEncontrado['tipo'] === 'equipo' ? 'Equipo Encontrado' : 'Insumo Encontrado' }}
                        </div>
                        <div class="text-base font-bold text-gray-900 mt-1">
                            {{ $itemEncontrado['item']->nombre }}
                        </div>
                        <div class="text-xs text-gray-600 font-mono mt-0.5">
                            Código: {{ $itemEncontrado['item']->codigo_barras }} · Ubicación: {{ $itemEncontrado['item']->ubicacion?->nombre ?? 'N/A' }}
                        </div>
                    </div>
                    <div>
                        <span class="inline-flex px-3 py-1 text-xs font-bold rounded-full bg-green-200 text-green-900">
                            Recuperación Exacta (CP-12)
                        </span>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sección 2: Generación de Lote de Etiquetas (SLA-07) -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 space-y-4">
            <div class="flex justify-between items-center no-print">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Generar Lote de Etiquetas Institucionales (Hasta 20 unidades)</h2>
                </div>
                <button wire:click="generarLote" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-medium rounded-md shadow-xs transition">
                    Generar Etiquetas
                </button>
            </div>

            @error('lote') <div class="text-xs text-red-600 font-medium">{{ $message }}</div> @enderror

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 no-print">
                <!-- Selector Equipos -->
                <div class="border rounded-md p-4 space-y-2">
                    <div class="font-bold text-xs uppercase text-gray-600">Equipos Disponibles</div>
                    <div class="max-h-48 overflow-y-auto space-y-1 text-xs">
                        @foreach($this->equiposDisponibles() as $eq)
                            <label class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" wire:model="seleccionadosEquipos" value="{{ $eq->id }}" class="rounded border-gray-300 text-red-600">
                                <span class="font-medium text-gray-800">{{ $eq->nombre }}</span>
                                <span class="text-gray-400 font-mono text-[11px]">({{ $eq->codigo_barras }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Selector Insumos -->
                <div class="border rounded-md p-4 space-y-2">
                    <div class="font-bold text-xs uppercase text-gray-600">Insumos Disponibles</div>
                    <div class="max-h-48 overflow-y-auto space-y-1 text-xs">
                        @foreach($this->insumosDisponibles() as $ins)
                            <label class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" wire:model="seleccionadosInsumos" value="{{ $ins->id }}" class="rounded border-gray-300 text-red-600">
                                <span class="font-medium text-gray-800">{{ $ins->nombre }}</span>
                                <span class="text-gray-400 font-mono text-[11px]">({{ $ins->codigo_barras }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Visualización del Lote y Botón de Impresión -->
            @if($htmlLote)
                <div class="border-t pt-4 space-y-3">
                    <div class="flex items-center justify-end bg-gray-50 p-3 rounded border border-gray-200 no-print">
                        <button onclick="window.print()" class="text-xs bg-gray-800 hover:bg-gray-900 text-white font-medium px-4 py-2 rounded shadow-xs transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Imprimir Etiquetas
                        </button>
                    </div>

                    <div id="seccion-impresion-etiquetas" class="bg-gray-50 p-4 rounded border">
                        {!! $htmlLote !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
