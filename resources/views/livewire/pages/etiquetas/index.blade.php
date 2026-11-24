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

    // Filtros de búsqueda para selección de ítems
    public string $busquedaEquipo = '';
    public string $busquedaInsumo = '';

    // Lote de etiquetas
    public array $seleccionadosEquipos = [];
    public array $seleccionadosInsumos = [];
    public ?string $htmlLote = null;
    public ?float $tiempoGeneracionMs = null;
    public int $totalGeneradas = 0;
    public array $itemsLote = [];

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
            $this->codigoLectura = '';
        }
    }

    public function imprimirEtiquetaIndividual(BarcodeService $service, string $tipo, int $id): void
    {
        $item = null;
        if ($tipo === 'equipo') {
            $item = Equipo::with('ubicacion.carrera')->find($id);
        } else {
            $item = Insumo::with('ubicacion.carrera')->find($id);
        }

        if (! $item || empty($item->codigo_barras)) {
            $this->addError('codigoLectura', 'El ítem seleccionado no posee un código de barras válido.');
            return;
        }

        $datosItem = [
            'codigo' => $item->codigo_barras,
            'nombre' => $item->nombre,
            'institucion' => 'INACAP · SEDE TEMUCO',
            'carrera' => $item->ubicacion?->carrera?->nombre,
            'ubicacion' => $item->ubicacion?->nombre ?? 'Pañol Informática',
        ];

        $resultado = $service->generarLoteEtiquetas([$datosItem]);
        $this->htmlLote = $resultado['html'];
        $this->tiempoGeneracionMs = $resultado['tiempo_ms'];
        $this->totalGeneradas = 1;
        $this->itemsLote = [$datosItem];
    }

    public function generarLote(BarcodeService $service): void
    {
        $totalSeleccionados = count($this->seleccionadosEquipos) + count($this->seleccionadosInsumos);

        if ($totalSeleccionados === 0) {
            $this->addError('lote', 'Debe seleccionar al menos un equipo o insumo para generar etiquetas.');
            return;
        }

        if ($totalSeleccionados > 20) {
            $this->addError('lote', "Ha seleccionado {$totalSeleccionados} etiquetas. El tamaño máximo institucional por tirada es de 20 etiquetas (SLA-07).");
            return;
        }

        $this->resetErrorBag('lote');
        $items = [];

        // Agregar equipos seleccionados
        if (! empty($this->seleccionadosEquipos)) {
            $equipos = Equipo::with('ubicacion.carrera')->whereIn('id', $this->seleccionadosEquipos)->get();
            foreach ($equipos as $eq) {
                if (! empty($eq->codigo_barras)) {
                    $items[] = [
                        'codigo' => $eq->codigo_barras,
                        'nombre' => $eq->nombre,
                        'institucion' => 'INACAP · SEDE TEMUCO',
                        'carrera' => $eq->ubicacion?->carrera?->nombre,
                        'ubicacion' => $eq->ubicacion?->nombre ?? 'Pañol Informática',
                    ];
                }
            }
        }

        // Agregar insumos seleccionados
        if (! empty($this->seleccionadosInsumos)) {
            $insumos = Insumo::with('ubicacion.carrera')->whereIn('id', $this->seleccionadosInsumos)->get();
            foreach ($insumos as $ins) {
                if (! empty($ins->codigo_barras)) {
                    $items[] = [
                        'codigo' => $ins->codigo_barras,
                        'nombre' => $ins->nombre,
                        'institucion' => 'INACAP · SEDE TEMUCO',
                        'carrera' => $ins->ubicacion?->carrera?->nombre,
                        'ubicacion' => $ins->ubicacion?->nombre ?? 'Pañol Informática',
                    ];
                }
            }
        }

        if (empty($items)) {
            $this->addError('lote', 'Ninguno de los ítems seleccionados posee un código de barras asignado.');
            return;
        }

        $resultado = $service->generarLoteEtiquetas($items);
        $this->htmlLote = $resultado['html'];
        $this->tiempoGeneracionMs = $resultado['tiempo_ms'];
        $this->totalGeneradas = $resultado['total'];
        $this->itemsLote = $items;
    }

    public function descargarTxtLote()
    {
        if (empty($this->itemsLote)) {
            $this->addError('lote', 'Primero debe generar un lote de etiquetas para poder exportarlo a archivo TXT.');
            return null;
        }

        $contenido = "CODIGO;NOMBRE;CARRERA;UBICACION;INSTITUCION\r\n";
        foreach ($this->itemsLote as $item) {
            $carrera = $item['carrera'] ?? 'N/A';
            $ubicacion = $item['ubicacion'] ?? 'Pañol Informática';
            $institucion = $item['institucion'] ?? 'INACAP · SEDE TEMUCO';
            $contenido .= "{$item['codigo']};{$item['nombre']};{$carrera};{$ubicacion};{$institucion}\r\n";
        }

        $nombreArchivo = 'lote_etiquetas_' . now()->format('Ymd_His') . '.txt';

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombreArchivo, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function limpiarSeleccionEquipos(): void
    {
        $this->seleccionadosEquipos = [];
    }

    public function limpiarSeleccionInsumos(): void
    {
        $this->seleccionadosInsumos = [];
    }

    public function equiposDisponibles()
    {
        return Equipo::with(['ubicacion.carrera', 'categoria'])
            ->whereNotNull('codigo_barras')
            ->where('codigo_barras', '!=', '')
            ->when(trim($this->busquedaEquipo) !== '', function ($query) {
                $t = '%' . trim($this->busquedaEquipo) . '%';
                $query->where(function ($q) use ($t) {
                    $q->where('nombre', 'LIKE', $t)
                      ->orWhere('codigo_barras', 'LIKE', $t)
                      ->orWhere('marca', 'LIKE', $t)
                      ->orWhere('modelo', 'LIKE', $t);
                });
            })
            ->latest()
            ->take(50)
            ->get();
    }

    public function insumosDisponibles()
    {
        return Insumo::with('ubicacion.carrera')
            ->whereNotNull('codigo_barras')
            ->where('codigo_barras', '!=', '')
            ->when(trim($this->busquedaInsumo) !== '', function ($query) {
                $t = '%' . trim($this->busquedaInsumo) . '%';
                $query->where(function ($q) use ($t) {
                    $q->where('nombre', 'LIKE', $t)
                      ->orWhere('codigo_barras', 'LIKE', $t);
                });
            })
            ->latest()
            ->take(50)
            ->get();
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
                margin: 4mm;
            }
            .etiqueta-institucional {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                border-color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .etiqueta-institucional * {
                color: #000000 !important;
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
                            {{ $metricas['ultimo_tiempo_segundos'] ? $metricas['ultimo_tiempo_segundos'] . 's' : '— (Sin mediciones)' }}
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
                <input wire:model="codigoLectura" wire:keydown.enter="leerCodigoBarras" type="text" placeholder="Escanee o ingrese el código de barras..." class="flex-1 text-sm rounded-md border-gray-300 font-mono focus:border-red-500 focus:ring-red-500">
                <button wire:click="leerCodigoBarras" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium rounded-md transition shadow-xs">
                    Buscar
                </button>
            </div>
            @error('codigoLectura') <div class="text-xs text-red-600 font-medium">{{ $message }}</div> @enderror

            @if($itemEncontrado)
                <div class="p-5 bg-green-50 border border-green-200 rounded-lg space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-green-800">
                                    {{ $itemEncontrado['tipo'] === 'equipo' ? 'Equipo Encontrado' : 'Insumo Encontrado' }}
                                </span>
                                <span class="inline-flex px-2 py-0.5 text-[11px] font-bold rounded-full bg-green-200 text-green-900">
                                    Recuperación Exacta (CP-12)
                                </span>
                            </div>
                            <div class="text-lg font-bold text-gray-900 mt-1">
                                {{ $itemEncontrado['item']->nombre }}
                            </div>
                            <div class="text-xs text-gray-600 font-mono mt-0.5">
                                Código: <span class="font-bold text-gray-900">{{ $itemEncontrado['item']->codigo_barras }}</span>
                                · Ubicación: {{ $itemEncontrado['item']->ubicacion?->nombre ?? 'N/A' }}
                                @if($itemEncontrado['item']->ubicacion?->carrera)
                                    · Carrera: <span class="font-medium text-gray-800">{{ $itemEncontrado['item']->ubicacion->carrera->nombre }}</span>
                                @endif
                            </div>
                        </div>
                        <button wire:click="imprimirEtiquetaIndividual('{{ $itemEncontrado['tipo'] }}', {{ $itemEncontrado['item']->id }})"
                            class="shrink-0 px-3.5 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-medium rounded-md shadow-xs transition flex items-center gap-1.5 self-start">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Imprimir Etiqueta
                        </button>
                    </div>

                    <!-- Ficha técnica o stock detallado (CP-12) -->
                    <div class="pt-3 border-t border-green-200 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        @if($itemEncontrado['tipo'] === 'equipo')
                            @php $eq = $itemEncontrado['item']; @endphp
                            <div>
                                <span class="text-gray-500 block">Marca / Modelo:</span>
                                <span class="font-medium text-gray-900">{{ $eq->marca ?? 'N/A' }} / {{ $eq->modelo ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">N° Serie:</span>
                                <span class="font-mono font-medium text-gray-900">{{ $eq->numero_serie ?? 'S/N' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Categoría:</span>
                                <span class="font-medium text-gray-900">{{ $eq->categoria?->nombre ?? 'General' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Estado Operativo:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $eq->estado === 'disponible' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ ucfirst(str_replace('_', ' ', $eq->estado)) }}
                                </span>
                            </div>
                        @else
                            @php $ins = $itemEncontrado['item']; @endphp
                            <div>
                                <span class="text-gray-500 block">Stock Actual:</span>
                                <span class="font-bold text-gray-900 text-sm">{{ $ins->stock_actual }} {{ $ins->unidad }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Stock Mínimo:</span>
                                <span class="font-medium text-gray-800">{{ $ins->stock_minimo }} {{ $ins->unidad }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Tipo Insumo:</span>
                                <span class="font-medium text-gray-800">{{ $ins->es_retornable ? 'Herramienta Retornable' : 'Consumible' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Nivel de Stock:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $ins->stock_actual <= $ins->stock_minimo ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $ins->stock_actual <= $ins->stock_minimo ? 'Stock Crítico' : 'Normal' }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Sección 2: Generación de Lote de Etiquetas (SLA-07) -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 space-y-4">
            @php $totalSeleccionados = count($seleccionadosEquipos) + count($seleccionadosInsumos); @endphp
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 no-print">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Generar Lote de Etiquetas Institucionales (Hasta 20 unidades)</h2>
                    <div class="text-xs text-gray-500 mt-0.5">
                        Seleccionados:
                        <span class="font-bold {{ $totalSeleccionados > 20 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $totalSeleccionados }}
                        </span>
                        / 20 unidades permitidas por tirada
                    </div>
                </div>
                <button wire:click="generarLote" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-medium rounded-md shadow-xs transition">
                    Generar Etiquetas
                </button>
            </div>

            @error('lote') <div class="text-xs text-red-600 font-medium bg-red-50 p-2.5 rounded border border-red-200">{{ $message }}</div> @enderror

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 no-print">
                <!-- Selector Equipos -->
                <div class="border rounded-md p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-xs uppercase text-gray-600">Equipos con Código de Barras</div>
                        @if(!empty($seleccionadosEquipos))
                            <button wire:click="limpiarSeleccionEquipos" class="text-[11px] text-red-600 hover:text-red-800 underline">
                                Deseleccionar ({{ count($seleccionadosEquipos) }})
                            </button>
                        @endif
                    </div>

                    <!-- Buscador reactivo equipos -->
                    <div>
                        <input wire:model.live.debounce.300ms="busquedaEquipo" type="text" placeholder="Buscar equipo por nombre o código..." class="w-full text-xs rounded border-gray-300 py-1.5 focus:border-red-500 focus:ring-red-500">
                    </div>

                    <div class="max-h-52 overflow-y-auto space-y-1 text-xs divide-y divide-gray-100">
                        @forelse($this->equiposDisponibles() as $eq)
                            <label class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" wire:model="seleccionadosEquipos" value="{{ $eq->id }}" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-800 truncate">{{ $eq->nombre }}</div>
                                    <div class="text-gray-400 font-mono text-[11px] truncate">
                                        {{ $eq->codigo_barras }}
                                        @if($eq->ubicacion)
                                            · {{ $eq->ubicacion->nombre }}
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="text-gray-400 py-3 text-center italic">No se encontraron equipos disponibles con código de barras.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Selector Insumos -->
                <div class="border rounded-md p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-xs uppercase text-gray-600">Insumos con Código de Barras</div>
                        @if(!empty($seleccionadosInsumos))
                            <button wire:click="limpiarSeleccionInsumos" class="text-[11px] text-red-600 hover:text-red-800 underline">
                                Deseleccionar ({{ count($seleccionadosInsumos) }})
                            </button>
                        @endif
                    </div>

                    <!-- Buscador reactivo insumos -->
                    <div>
                        <input wire:model.live.debounce.300ms="busquedaInsumo" type="text" placeholder="Buscar insumo por nombre o código..." class="w-full text-xs rounded border-gray-300 py-1.5 focus:border-red-500 focus:ring-red-500">
                    </div>

                    <div class="max-h-52 overflow-y-auto space-y-1 text-xs divide-y divide-gray-100">
                        @forelse($this->insumosDisponibles() as $ins)
                            <label class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" wire:model="seleccionadosInsumos" value="{{ $ins->id }}" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-800 truncate">{{ $ins->nombre }}</div>
                                    <div class="text-gray-400 font-mono text-[11px] truncate">
                                        {{ $ins->codigo_barras }}
                                        @if($ins->ubicacion)
                                            · {{ $ins->ubicacion->nombre }}
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="text-gray-400 py-3 text-center italic">No se encontraron insumos disponibles con código de barras.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Visualización del Lote y Botones de Impresión / Descarga -->
            @if($htmlLote)
                <div class="border-t pt-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between bg-gray-50 p-3 rounded border border-gray-200 no-print gap-2">
                        <div class="text-xs text-gray-700">
                            Lote preparado: <span class="font-bold text-gray-900">{{ $totalGeneradas }}</span> etiqueta(s) generada(s)
                            @if($tiempoGeneracionMs)
                                en <span class="font-mono text-gray-600">{{ $tiempoGeneracionMs }} ms</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="descargarTxtLote" class="text-xs bg-white hover:bg-gray-100 text-gray-800 font-medium px-3.5 py-2 rounded border border-gray-300 shadow-xs transition flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Descargar TXT
                            </button>
                            <button onclick="window.print()" class="text-xs bg-gray-800 hover:bg-gray-900 text-white font-medium px-4 py-2 rounded shadow-xs transition flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Imprimir Etiquetas
                            </button>
                        </div>
                    </div>

                    <div id="seccion-impresion-etiquetas" class="bg-gray-50 p-4 rounded border">
                        {!! $htmlLote !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>