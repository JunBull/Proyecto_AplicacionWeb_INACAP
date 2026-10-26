<?php

use App\Models\Equipo;
use App\Models\Insumo;
use App\Services\BarcodeService;
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
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Etiquetado Institucional y Lectura de Códigos (CODE128)</h1>
                <p class="text-sm text-gray-500 mt-1">Generación de códigos lineales 1D estandarizados, cumplimiento SLA-07 (≤ 10s por lote) y lectura óptica (CP-12).</p>
            </div>
        </div>

        <!-- Sección 1: Lectura óptica / Escaneo (CP-12) -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 space-y-4">
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
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Generar Lote de Etiquetas Institucionales (Hasta 20 unidades)</h2>
                    <p class="text-xs text-gray-500">Renderizado de alta fidelidad cumpliendo SLA-07 (lote completo generado en ≤ 10 segundos).</p>
                </div>
                <button wire:click="generarLote" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-medium rounded-md">
                    Generar Lote Seleccionado
                </button>
            </div>

            @error('lote') <div class="text-xs text-red-600 font-medium">{{ $message }}</div> @enderror

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

            <!-- Visualización del Lote y Métrica SLA-07 -->
            @if($htmlLote)
                <div class="border-t pt-4 space-y-3">
                    <div class="flex items-center justify-between bg-blue-50 p-3 rounded border border-blue-200">
                        <div class="text-xs text-blue-900">
                            <strong>Métrica SLA-07:</strong> Lote de <strong>{{ $totalGeneradas }}</strong> etiquetas generado en <strong>{{ $tiempoGeneracionMs }} ms</strong> (Meta: &le; 10.000 ms).
                        </div>
                        <button onclick="window.print()" class="text-xs bg-gray-800 hover:bg-gray-900 text-white font-medium px-3 py-1.5 rounded">
                            Imprimir Etiquetas
                        </button>
                    </div>

                    <div class="bg-gray-50 p-4 rounded border">
                        {!! $htmlLote !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
