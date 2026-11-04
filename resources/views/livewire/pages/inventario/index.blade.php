<?php

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Ubicacion;
use App\Services\InventarioService;
use App\Services\UbicacionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $buscar = '';
    public bool $soloCriticos = false;
    public ?int $filtroUbicacionId = null;

    // Modal Crear Insumo
    public bool $mostrarModalCrear = false;
    public string $codigo_barras = '';
    public string $nombre = '';
    public string $unidad = 'unidad';
    public bool $es_retornable = false;
    public float $stock_actual = 0;
    public float $stock_minimo = 5;
    public ?int $ubicacion_id = null;

    // Modal Movimiento (Ingreso / Salida / Ajuste)
    public bool $mostrarModalMovimiento = false;
    public ?int $insumoSeleccionadoId = null;
    public string $tipoOperacion = 'ingreso'; // ingreso, salida, ajuste
    public float $cantidadMovimiento = 1;
    public string $justificacionAjuste = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Insumo::class);
        $primera = Ubicacion::first();
        if ($primera) {
            $this->ubicacion_id = $primera->id;
        }
    }

    public function insumos()
    {
        $query = Insumo::with(['ubicacion.carrera', 'ubicacion.padre']);

        if (trim($this->buscar) !== '') {
            $t = '%' . trim($this->buscar) . '%';
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'LIKE', $t)
                  ->orWhere('codigo_barras', 'LIKE', $t);
            });
        }

        if ($this->soloCriticos) {
            $query->whereRaw('stock_actual <= stock_minimo');
        }

        if ($this->filtroUbicacionId) {
            $query->where('ubicacion_id', $this->filtroUbicacionId);
        }

        return $query->orderBy('nombre')->get();
    }

    public function ubicaciones()
    {
        return Ubicacion::with('carrera')->orderBy('nombre')->get();
    }

    public function totalCriticos(): int
    {
        return Insumo::whereRaw('stock_actual <= stock_minimo')->count();
    }

    public function insumosCriticos()
    {
        return Insumo::with(['ubicacion.carrera'])
            ->whereRaw('stock_actual <= stock_minimo')
            ->orderBy('stock_actual')
            ->get();
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('create', Insumo::class);
        $this->resetErrorBag();
        $this->codigo_barras = 'INS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $this->nombre = '';
        $this->unidad = 'unidad';
        $this->es_retornable = false;
        $this->stock_actual = 0;
        $this->stock_minimo = 5;
        $this->mostrarModalCrear = true;
    }

    public function guardarInsumo(InventarioService $inventarioService): void
    {
        Gate::authorize('create', Insumo::class);

        try {
            $inventarioService->registrarInsumo([
                'codigo_barras' => $this->codigo_barras,
                'nombre' => $this->nombre,
                'unidad' => $this->unidad,
                'es_retornable' => $this->es_retornable,
                'stock_actual' => $this->stock_actual,
                'stock_minimo' => $this->stock_minimo,
                'ubicacion_id' => (int) $this->ubicacion_id,
            ]);

            $this->mostrarModalCrear = false;
            session()->flash('mensaje', 'Insumo registrado correctamente en su ubicación física granular.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }
        }
    }

    public function abrirModalMovimiento(int $insumoId, string $operacion): void
    {
        $this->resetErrorBag();
        $this->insumoSeleccionadoId = $insumoId;
        $this->tipoOperacion = $operacion;
        $this->cantidadMovimiento = 1;
        $this->justificacionAjuste = '';
        $this->mostrarModalMovimiento = true;
    }

    public function procesarMovimiento(InventarioService $inventarioService): void
    {
        $insumo = Insumo::findOrFail($this->insumoSeleccionadoId);
        Gate::authorize('ajustarStock', $insumo);

        try {
            if ($this->tipoOperacion === 'ingreso') {
                $inventarioService->registrarIngreso(
                    $insumo,
                    (float) $this->cantidadMovimiento,
                    Auth::user(),
                    'ingreso_manual'
                );
                session()->flash('mensaje', 'Ingreso de existencias procesado y registrado en kardex.');
            } elseif ($this->tipoOperacion === 'salida') {
                $inventarioService->registrarSalida(
                    $insumo,
                    (float) $this->cantidadMovimiento,
                    Auth::user(),
                    'salida_consumo'
                );
                session()->flash('mensaje', 'Salida de insumo debitada correctamente del stock.');
            } elseif ($this->tipoOperacion === 'ajuste') {
                $inventarioService->registrarAjuste(
                    $insumo,
                    (float) $this->cantidadMovimiento,
                    Auth::user(),
                    $this->justificacionAjuste ?: 'Ajuste operativo'
                );
                session()->flash('mensaje', 'Ajuste de inventario aplicado con éxito.');
            }

            $this->mostrarModalMovimiento = false;
        } catch (\Exception $e) {
            $this->addError('movimiento', $e->getMessage());
        }
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado Renombrado (Sección 3.3.1) -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Inventario y Mantención de Equipo</h1>
                <p class="text-sm text-gray-500 mt-1">Control granular de ubicaciones (mueble, estante, cajón), transacciones ACID y monitor de stock crítico con cotización rápida (KPI-04 / CP-24).</p>
            </div>
            @if(Auth::user()->rol !== 'docente')
                <button wire:click="abrirModalCrear" class="inline-flex items-center px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-md shadow-sm transition">
                    <svg class="w-5 h-5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Insumo
                </button>
            @endif
        </div>

        @if(session()->has('mensaje'))
            <div class="p-4 bg-green-50 border-l-4 border-green-600 text-green-800 text-sm rounded-r-md">
                {{ session('mensaje') }}
            </div>
        @endif

        <!-- Monitor reactivo y Dashboard de Stock Crítico con Acción Cotizar (KPI-04, CP-24, Sección 3.3.2) -->
        @php
            $criticos = $this->insumosCriticos();
            $criticosCount = $criticos->count();
        @endphp
        @if($criticosCount > 0)
            <div class="bg-red-50/90 border border-red-200 rounded-xl p-5 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-red-200/80 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-red-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <div class="font-extrabold text-base text-red-950">Alertas de Stock Crítico (KPI-04 / CP-24)</div>
                            <div class="text-xs text-red-800">
                                Existen <strong>{{ $criticosCount }}</strong> insumo(s) con existencias iguales o inferiores al umbral mínimo operativo.
                            </div>
                        </div>
                    </div>
                    <button wire:click="$toggle('soloCriticos')" class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition {{ $soloCriticos ? 'bg-red-700 text-white shadow-xs' : 'bg-red-200 text-red-900 hover:bg-red-300' }}">
                        {{ $soloCriticos ? 'Ver Todo el Catálogo' : 'Filtrar Solo Críticos en Tabla' }}
                    </button>
                </div>

                <!-- Tarjetas de Ítems Críticos con Botón "Cotizar" Directo -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($criticos as $critico)
                        @php
                            $sugerido = max(1, (int)(($critico->stock_minimo * 2) - $critico->stock_actual));
                        @endphp
                        <div class="bg-white rounded-lg border border-red-200 p-3.5 flex flex-col justify-between shadow-2xs">
                            <div class="space-y-1">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-mono text-[11px] font-bold text-slate-500">{{ $critico->codigo_barras }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-red-100 text-red-800">
                                        Crítico
                                    </span>
                                </div>
                                <h4 class="font-bold text-slate-900 text-xs sm:text-sm leading-tight">{{ $critico->nombre }}</h4>
                                <div class="text-[11px] text-slate-500">
                                    {{ $critico->ubicacion?->nombre ?? 'Sin ubicación' }}
                                    @if($critico->ubicacion?->carrera)
                                        &bull; {{ $critico->ubicacion->carrera->codigo }}
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 pt-1 text-xs">
                                    <span class="text-red-700 font-extrabold">Stock: {{ (float)$critico->stock_actual }} {{ $critico->unidad }}</span>
                                    <span class="text-slate-400">/ Mín: {{ (float)$critico->stock_minimo }}</span>
                                </div>
                            </div>

                            <div class="pt-3 mt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                                <span class="text-[11px] text-slate-500">
                                    Sugerido: <strong>{{ $sugerido }} {{ $critico->unidad }}</strong>
                                </span>
                                <a
                                    href="{{ route('panol.cotizaciones', ['insumo_id' => $critico->id]) }}"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white rounded-md text-xs font-bold shadow-2xs transition"
                                    wire:navigate
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    Cotizar
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Filtros y Búsqueda -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Buscar</label>
                <input wire:model.live.debounce.300ms="buscar" type="text" placeholder="Nombre de insumo o código de barras..." class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Ubicación Física</label>
                <select wire:model.live="filtroUbicacionId" class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">
                    <option value="">Todas las ubicaciones</option>
                    @foreach($this->ubicaciones() as $ub)
                        <option value="{{ $ub->id }}">{{ $ub->nombre }} ({{ $ub->tipo }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center text-sm text-gray-700 select-none cursor-pointer">
                    <input wire:model.live="soloCriticos" type="checkbox" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                    <span class="ml-2">Mostrar únicamente stock crítico (&le; mín)</span>
                </label>
            </div>
        </div>

        <!-- Tabla de Insumos -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                        <tr>
                            <th class="px-6 py-3 text-left">Código</th>
                            <th class="px-6 py-3 text-left">Insumo / Material</th>
                            <th class="px-6 py-3 text-left">Ubicación Granular (CP-11)</th>
                            <th class="px-6 py-3 text-center">Stock Actual</th>
                            <th class="px-6 py-3 text-center">Stock Mínimo</th>
                            <th class="px-6 py-3 text-center">Tipo</th>
                            <th class="px-6 py-3 text-right">Transacciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($this->insumos() as $ins)
                            @php $enCritico = (float) $ins->stock_actual <= (float) $ins->stock_minimo; @endphp
                            <tr class="hover:bg-gray-50 transition {{ $enCritico ? 'bg-red-50/40' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-medium text-gray-900">
                                    {{ $ins->codigo_barras }}
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $ins->nombre }}
                                    <div class="text-xs text-gray-500">Unidad: {{ $ins->unidad }}</div>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-600">
                                    @php
                                        $rutaNodos = [];
                                        $curr = $ins->ubicacion;
                                        while ($curr) {
                                            array_unshift($rutaNodos, $curr->nombre . ' (' . $curr->tipo . ')');
                                            $curr = $curr->padre;
                                        }
                                    @endphp
                                    <div class="font-medium text-gray-800">{{ $ins->ubicacion?->nombre }}</div>
                                    <div class="text-gray-400 text-[11px]">{{ implode(' > ', $rutaNodos) }}</div>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold {{ $enCritico ? 'bg-red-50 text-red-800 border border-red-300' : 'bg-slate-100 text-slate-800 border border-slate-200' }}">
                                        @if($enCritico)
                                            <svg class="w-3.5 h-3.5 text-red-700 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                        {{ number_format((float) $ins->stock_actual, 0) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-xs text-gray-500 whitespace-nowrap">
                                    {{ number_format((float) $ins->stock_minimo, 0) }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    @if($ins->es_retornable)
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-800">Retornable</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-600">Consumible</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                    @if(Auth::user()->rol !== 'docente')
                                        <button wire:click="abrirModalMovimiento({{ $ins->id }}, 'ingreso')" class="text-xs font-semibold text-green-700 hover:text-green-900">+ Ingreso</button>
                                        <button wire:click="abrirModalMovimiento({{ $ins->id }}, 'salida')" class="text-xs font-semibold text-red-700 hover:text-red-900">- Salida</button>
                                        <button wire:click="abrirModalMovimiento({{ $ins->id }}, 'ajuste')" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Ajuste</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">No se encontraron insumos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Registrar Insumo -->
    @if($mostrarModalCrear)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-xl w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Registrar Nuevo Insumo / Material</h3>
                    <button wire:click="$set('mostrarModalCrear', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Nombre Estandarizado</label>
                        <input wire:model="nombre" type="text" placeholder="Ej: Cable UTP Categoría 6 Patch Cord 2m" class="w-full text-sm rounded-md border-gray-300">
                        @error('nombre') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Código de Barras</label>
                            <input wire:model="codigo_barras" type="text" class="w-full text-sm rounded-md border-gray-300 font-mono">
                            @error('codigo_barras') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Unidad de Medida</label>
                            <input wire:model="unidad" type="text" placeholder="unidad, metro, caja" class="w-full text-sm rounded-md border-gray-300">
                            @error('unidad') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Stock Inicial</label>
                            <input wire:model="stock_actual" type="number" step="1" min="0" class="w-full text-sm rounded-md border-gray-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Stock Mínimo (Umbral KPI-04)</label>
                            <input wire:model="stock_minimo" type="number" step="1" min="0" class="w-full text-sm rounded-md border-gray-300">
                            @error('stock_minimo') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Ubicación Física Detallada (CP-11)</label>
                        <select wire:model="ubicacion_id" class="w-full text-sm rounded-md border-gray-300">
                            @foreach($this->ubicaciones() as $ub)
                                <option value="{{ $ub->id }}">{{ $ub->nombre }} ({{ $ub->tipo }}) - {{ $ub->carrera?->codigo }}</option>
                            @endforeach
                        </select>
                        @error('ubicacion_id') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="inline-flex items-center text-sm text-gray-700 cursor-pointer">
                            <input wire:model="es_retornable" type="checkbox" class="rounded border-gray-300 text-red-600">
                            <span class="ml-2">Es retornable (requiere devolución formal en mesón)</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalCrear', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="guardarInsumo" type="button" class="px-4 py-2 text-sm bg-red-700 hover:bg-red-800 text-white font-medium rounded-md">Guardar Insumo</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Movimiento Transaccional (Ingreso / Salida / Ajuste) -->
    @if($mostrarModalMovimiento)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $tipoOperacion === 'ingreso' ? 'Registrar Ingreso de Stock' : ($tipoOperacion === 'salida' ? 'Registrar Salida de Consumo' : 'Ajuste de Existencias') }}
                    </h3>
                    <button wire:click="$set('mostrarModalMovimiento', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                @error('movimiento')
                    <div class="p-3 bg-red-50 text-red-800 text-xs rounded border border-red-200">
                        {{ $message }}
                    </div>
                @enderror

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                        {{ $tipoOperacion === 'ajuste' ? 'Nuevo Stock Físico Verificado' : 'Cantidad a Mover (CP-13)' }}
                    </label>
                    <input wire:model="cantidadMovimiento" type="number" step="1" min="{{ $tipoOperacion === 'ajuste' ? 0 : 1 }}" class="w-full text-sm rounded-md border-gray-300">
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $tipoOperacion === 'salida' ? 'No se permiten cantidades superiores a las existencias ni valores negativos.' : 'Operación atómica registrada en kardex inmutable.' }}
                    </p>
                </div>

                @if($tipoOperacion === 'ajuste')
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Justificación del Ajuste</label>
                        <textarea wire:model="justificacionAjuste" rows="2" class="w-full text-sm rounded-md border-gray-300" placeholder="Motivo de la discrepancia detectada..."></textarea>
                    </div>
                @endif

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalMovimiento', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="procesarMovimiento" type="button" class="px-4 py-2 text-sm font-medium rounded-md text-white {{ $tipoOperacion === 'ingreso' ? 'bg-green-700 hover:bg-green-800' : ($tipoOperacion === 'salida' ? 'bg-red-700 hover:bg-red-800' : 'bg-blue-700 hover:bg-blue-800') }}">
                        Confirmar Movimiento
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
