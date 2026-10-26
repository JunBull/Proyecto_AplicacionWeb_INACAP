<?php

use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\SolicitudMaterial;
use App\Services\PrestamoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $buscarDisponibilidad = '';
    public string $filtroTipoItem = 'todos'; // todos, equipos, insumos
    public string $filtroEstadoSolicitud = 'todos';

    // Formulario de nueva solicitud
    public bool $mostrarModalCrear = false;
    public string $fecha_requerida = '';
    public string $observaciones = '';
    public array $itemsSeleccionados = []; // [['tipo' => 'insumo'|'equipo', 'id' => int, 'nombre' => string, 'cantidad' => float, 'es_retornable' => bool]]

    // Modal de detalle de solicitud
    public bool $mostrarModalDetalle = false;
    public ?int $solicitudDetalleId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', SolicitudMaterial::class);
        $this->fecha_requerida = Carbon::tomorrow()->toDateString();
    }

    public function abrirModalCrear(): void
    {
        $this->resetValidation();
        $this->fecha_requerida = Carbon::tomorrow()->toDateString();
        $this->observaciones = '';
        $this->itemsSeleccionados = [];
        $this->mostrarModalCrear = true;
    }

    public function agregarItem(string $tipo, int $id): void
    {
        // Verificar que no esté ya agregado
        foreach ($this->itemsSeleccionados as $item) {
            if ($item['tipo'] === $tipo && $item['id'] === $id) {
                session()->flash('info_item', 'El ítem ya fue agregado a la lista de solicitud.');
                return;
            }
        }

        if ($tipo === 'equipo') {
            $equipo = Equipo::find($id);
            if (! $equipo || $equipo->estado !== 'disponible') {
                session()->flash('error_item', 'El equipo seleccionado no se encuentra disponible actualmente.');
                return;
            }
            $this->itemsSeleccionados[] = [
                'tipo' => 'equipo',
                'id' => $equipo->id,
                'nombre' => $equipo->nombre . ' (' . $equipo->codigo_barras . ')',
                'cantidad' => 1,
                'es_retornable' => true,
            ];
        } else {
            $insumo = Insumo::find($id);
            if (! $insumo || (float) $insumo->stock_actual <= 0) {
                session()->flash('error_item', 'El insumo seleccionado no tiene stock disponible.');
                return;
            }
            $this->itemsSeleccionados[] = [
                'tipo' => 'insumo',
                'id' => $insumo->id,
                'nombre' => $insumo->nombre . ' [' . $insumo->unidad . ']',
                'cantidad' => 1,
                'es_retornable' => (bool) $insumo->es_retornable,
                'stock_max' => (float) $insumo->stock_actual,
            ];
        }
    }

    public function removerItem(int $index): void
    {
        unset($this->itemsSeleccionados[$index]);
        $this->itemsSeleccionados = array_values($this->itemsSeleccionados);
    }

    public function registrarSolicitud(PrestamoService $prestamoService): void
    {
        Gate::authorize('create', SolicitudMaterial::class);

        $this->validate([
            'fecha_requerida' => 'required|date|after_or_equal:today',
            'itemsSeleccionados' => 'required|array|min:1',
            'itemsSeleccionados.*.cantidad' => 'required|numeric|min:1',
        ], [
            'fecha_requerida.required' => 'La fecha requerida es obligatoria.',
            'fecha_requerida.after_or_equal' => 'La fecha requerida no puede ser anterior a hoy.',
            'itemsSeleccionados.min' => 'Debe agregar al menos un ítem a la solicitud.',
            'itemsSeleccionados.*.cantidad.min' => 'La cantidad mínima por ítem es 1.',
        ]);

        $itemsFormateados = [];
        foreach ($this->itemsSeleccionados as $item) {
            $itemsFormateados[] = [
                'insumo_id' => $item['tipo'] === 'insumo' ? $item['id'] : null,
                'equipo_id' => $item['tipo'] === 'equipo' ? $item['id'] : null,
                'cantidad' => (float) $item['cantidad'],
            ];
        }

        try {
            $prestamoService->crearSolicitud(
                Auth::user(),
                [
                    'fecha_requerida' => $this->fecha_requerida,
                    'observaciones' => $this->observaciones,
                ],
                $itemsFormateados
            );

            session()->flash('mensaje', '¡Solicitud registrada exitosamente! Se encuentra en la cola de revisión de pañol.');
            $this->mostrarModalCrear = false;
            $this->itemsSeleccionados = [];
            $this->observaciones = '';
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function verDetalle(int $solicitudId): void
    {
        $solicitud = SolicitudMaterial::findOrFail($solicitudId);
        Gate::authorize('view', $solicitud);

        $this->solicitudDetalleId = $solicitudId;
        $this->mostrarModalDetalle = true;
    }

    public function solicitudes()
    {
        $query = SolicitudMaterial::with(['detalles.insumo', 'detalles.equipo', 'entregas.devoluciones'])
            ->where('solicitante_id', Auth::id())
            ->orderByDesc('id');

        if ($this->filtroEstadoSolicitud !== 'todos') {
            $query->where('estado', $this->filtroEstadoSolicitud);
        }

        return $query->get();
    }

    public function disponibilidad(PrestamoService $prestamoService)
    {
        return $prestamoService->consultarDisponibilidad($this->buscarDisponibilidad);
    }
}; ?>

<div class="py-6 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <!-- Encabezado adaptable a dispositivos móviles (CP-19, PI-07) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-7 h-7 text-red-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                Portal Docente: Solicitud de Materiales
            </h1>
            <p class="text-sm text-gray-500 mt-1">Consulte disponibilidad en pañol y registre requerimientos de equipos e insumos.</p>
        </div>
        <button
            wire:click="abrirModalCrear"
            class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium text-sm rounded-lg shadow-sm transition active:scale-95"
        >
            <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nueva Solicitud
        </button>
    </div>

    <!-- Alertas Flash -->
    @if(session()->has('mensaje'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <span class="text-sm font-medium">{{ session('mensaje') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Grilla principal: Consulta de Disponibilidad y Mis Solicitudes -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna Izquierda: Consulta de Catálogo y Disponibilidad -->
        <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="mb-3">
                <h2 class="text-base font-semibold text-gray-900 flex items-center justify-between">
                    <span>Disponibilidad en Pañol</span>
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-mono">En vivo</span>
                </h2>
                <p class="text-xs text-gray-500 mt-0.5">Revise inventario físico antes de solicitar.</p>
            </div>

            <!-- Buscador reactivo -->
            <div class="relative mb-3">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscarDisponibilidad"
                    placeholder="Buscar equipo o insumo..."
                    class="w-full text-sm pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                />
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <!-- Listado con pestañas de Equipos e Insumos -->
            @php $disp = $this->disponibilidad(app(PrestamoService::class)); @endphp
            <div class="space-y-4 max-h-[600px] overflow-y-auto pr-1">
                <!-- Sección Equipos Disponibles -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2 flex items-center justify-between">
                        <span>Equipos Disponibles</span>
                        <span class="bg-blue-100 text-blue-700 text-[11px] px-1.5 py-0.5 rounded">{{ $disp['equipos']->count() }}</span>
                    </h3>
                    @if($disp['equipos']->isEmpty())
                        <p class="text-xs text-gray-400 italic py-2">No hay equipos disponibles coincidentes.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($disp['equipos'] as $eq)
                                <div class="p-2.5 rounded-lg border border-gray-100 bg-gray-50/70 hover:bg-white hover:border-gray-300 transition text-xs">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-semibold text-gray-800">{{ $eq->nombre }}</div>
                                            <div class="text-[11px] text-gray-500">{{ $eq->marca }} {{ $eq->modelo }} &bull; <span class="font-mono text-gray-600">{{ $eq->codigo_barras }}</span></div>
                                            <div class="text-[10px] text-gray-400 mt-0.5">Ubicación: {{ $eq->ubicacion->nombre ?? 'Pañol Central' }}</div>
                                        </div>
                                        <button
                                            wire:click="agregarItem('equipo', {{ $eq->id }})"
                                            class="shrink-0 px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded text-[11px] font-medium transition"
                                            title="Agregar a solicitud"
                                        >
                                            + Pedir
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Sección Insumos en Stock -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2 flex items-center justify-between">
                        <span>Insumos con Existencias</span>
                        <span class="bg-emerald-100 text-emerald-700 text-[11px] px-1.5 py-0.5 rounded">{{ $disp['insumos']->count() }}</span>
                    </h3>
                    @if($disp['insumos']->isEmpty())
                        <p class="text-xs text-gray-400 italic py-2">No se encontraron insumos.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($disp['insumos'] as $ins)
                                <div class="p-2.5 rounded-lg border border-gray-100 bg-gray-50/70 hover:bg-white hover:border-gray-300 transition text-xs">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-semibold text-gray-800">{{ $ins->nombre }}</div>
                                            <div class="text-[11px] text-gray-500">
                                                Stock: <span class="font-bold {{ (float)$ins->stock_actual <= (float)$ins->stock_minimo ? 'text-amber-600' : 'text-emerald-600' }}">{{ $ins->stock_actual }} {{ $ins->unidad }}</span>
                                                &bull;
                                                @if($ins->es_retornable)
                                                    <span class="text-indigo-600 font-medium">Retornable</span>
                                                @else
                                                    <span class="text-gray-500 font-medium">Consumible</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if((float)$ins->stock_actual > 0)
                                            <button
                                                wire:click="agregarItem('insumo', {{ $ins->id }})"
                                                class="shrink-0 px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded text-[11px] font-medium transition"
                                                title="Agregar a solicitud"
                                            >
                                                + Pedir
                                            </button>
                                        @else
                                            <span class="text-[10px] text-gray-400 italic">Agotado</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Columna Central/Derecha: Mis Solicitudes Registradas -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 border-b border-gray-100 pb-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Mis Solicitudes Realizadas</h2>
                    <p class="text-xs text-gray-500">Historial y estado de atención en mesón de pañol.</p>
                </div>
                <!-- Filtro por Estado -->
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-500 font-medium">Filtrar:</label>
                    <select
                        wire:model.live="filtroEstadoSolicitud"
                        class="text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 py-1.5"
                    >
                        <option value="todos">Todas</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="aprobada">Aprobadas</option>
                        <option value="entregada_abierta">Préstamo Activo</option>
                        <option value="cerrada">Cerradas</option>
                        <option value="rechazada">Rechazadas</option>
                        <option value="sin_disponibilidad">Sin Disponibilidad</option>
                    </select>
                </div>
            </div>

            @php $listaSolicitudes = $this->solicitudes(); @endphp

            @if($listaSolicitudes->isEmpty())
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="text-sm font-medium text-gray-700">No se encontraron solicitudes</h3>
                    <p class="text-xs text-gray-400 mt-1">Presione "Nueva Solicitud" para solicitar materiales.</p>
                </div>
            @else
                <!-- Lista responsiva adaptable a móviles -->
                <div class="space-y-3">
                    @foreach($listaSolicitudes as $sol)
                        <div class="p-4 rounded-xl border border-gray-200 hover:border-gray-300 transition bg-white shadow-xs">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-gray-900 text-sm">#SOL-{{ str_pad($sol->id, 5, '0', STR_PAD_LEFT) }}</span>
                                    <span class="text-xs text-gray-400">&bull; Requerida: <strong class="text-gray-700">{{ $sol->fecha_requerida->format('d/m/Y') }}</strong></span>
                                </div>
                                <div>
                                    @php
                                        $badgeClass = match($sol->estado) {
                                            'pendiente' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'aprobada' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'entregada_abierta' => 'bg-purple-100 text-purple-800 border-purple-200',
                                            'cerrada' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'rechazada' => 'bg-red-100 text-red-800 border-red-200',
                                            'sin_disponibilidad' => 'bg-gray-100 text-gray-700 border-gray-300',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                        $labelEstado = match($sol->estado) {
                                            'pendiente' => 'Pendiente de Revisión',
                                            'aprobada' => 'Aprobada (Lista para Retiro)',
                                            'entregada_abierta' => 'Préstamo Abierto',
                                            'cerrada' => 'Cerrada / Finalizada',
                                            'rechazada' => 'Rechazada',
                                            'sin_disponibilidad' => 'Sin Disponibilidad',
                                            default => $sol->estado,
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                                        {{ $labelEstado }}
                                    </span>
                                </div>
                            </div>

                            <!-- Resumen de Ítems -->
                            <div class="mt-2.5 text-xs text-gray-600">
                                <ul class="list-disc list-inside space-y-0.5">
                                    @foreach($sol->detalles as $det)
                                        <li>
                                            @if($det->equipo)
                                                Equipo: <strong class="text-gray-800">{{ $det->equipo->nombre }}</strong> ({{ $det->equipo->codigo_barras }})
                                            @elseif($det->insumo)
                                                Insumo: <strong class="text-gray-800">{{ $det->insumo->nombre }}</strong> &times; {{ (float)$det->cantidad_solicitada }} {{ $det->insumo->unidad }}
                                            @endif
                                            @if($det->estado_devolucion === 'pendiente')
                                                <span class="text-purple-600 font-semibold">[Devolución pendiente]</span>
                                            @elseif($det->estado_devolucion === 'con_danos')
                                                <span class="text-red-600 font-semibold">[Devuelto con daños]</span>
                                            @elseif($det->estado_devolucion === 'completa')
                                                <span class="text-emerald-600 font-semibold">[Devuelto OK]</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                @if($sol->observaciones)
                                    <p class="text-[11px] text-gray-500 italic mt-1.5 bg-gray-50 p-1.5 rounded border border-gray-100">
                                        "{{ $sol->observaciones }}"
                                    </p>
                                @endif
                            </div>

                            <!-- Botón Detalle -->
                            <div class="mt-3 pt-2 border-t border-gray-100 flex justify-end">
                                <button
                                    wire:click="verDetalle({{ $sol->id }})"
                                    class="text-xs text-red-600 hover:text-red-800 font-semibold flex items-center gap-1"
                                >
                                    Ver trazabilidad completa &rarr;
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- MODAL: Crear Solicitud (Responsive Móvil / Desktop) -->
    @if($mostrarModalCrear)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-5 py-4 bg-red-600 text-white flex items-center justify-between">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Nueva Solicitud de Materiales
                    </h3>
                    <button wire:click="$set('mostrarModalCrear', false)" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto">
                    @if(session()->has('info_item'))
                        <div class="p-2.5 rounded bg-blue-50 text-blue-700 text-xs">
                            {{ session('info_item') }}
                        </div>
                    @endif
                    @if(session()->has('error_item'))
                        <div class="p-2.5 rounded bg-red-50 text-red-700 text-xs">
                            {{ session('error_item') }}
                        </div>
                    @endif

                    <!-- Fecha Requerida -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Fecha Requerida de Retiro <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            wire:model="fecha_requerida"
                            min="{{ date('Y-m-d') }}"
                            class="w-full text-sm border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                        />
                        @error('fecha_requerida')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Ítems Agregados -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-xs font-semibold text-gray-700">
                                Ítems a Solicitar <span class="text-red-500">*</span>
                            </label>
                            <span class="text-[11px] text-gray-400">Total ítems: {{ count($itemsSeleccionados) }}</span>
                        </div>

                        @if(empty($itemsSeleccionados))
                            <div class="p-4 border-2 border-dashed border-gray-200 rounded-lg text-center text-xs text-gray-500">
                                No ha seleccionado ningún material. Agregue insumos o equipos desde la columna izquierda "Disponibilidad en Pañol".
                            </div>
                        @else
                            <div class="space-y-2 border border-gray-200 rounded-lg p-3 bg-gray-50/50">
                                @foreach($itemsSeleccionados as $idx => $it)
                                    <div class="flex items-center justify-between gap-2 p-2 bg-white rounded border border-gray-200 text-xs">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-800 truncate">{{ $it['nombre'] }}</div>
                                            <div class="text-[10px] text-gray-400">
                                                {{ $it['tipo'] === 'equipo' ? 'Equipo retornable' : ($it['es_retornable'] ? 'Insumo retornable' : 'Insumo consumible') }}
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($it['tipo'] === 'insumo')
                                                <div class="w-20">
                                                    <input
                                                        type="number"
                                                        step="1"
                                                        min="1"
                                                        wire:model="itemsSeleccionados.{{ $idx }}.cantidad"
                                                        class="w-full text-xs py-1 px-2 border-gray-300 rounded text-center"
                                                        title="Cantidad solicitada"
                                                    />
                                                </div>
                                            @else
                                                <span class="text-xs font-bold text-gray-600 px-2 py-1 bg-gray-100 rounded">1 un.</span>
                                            @endif
                                            <button
                                                type="button"
                                                wire:click="removerItem({{ $idx }})"
                                                class="text-red-500 hover:text-red-700 p-1"
                                                title="Eliminar ítem"
                                            >
                                                &times;
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @error('itemsSeleccionados')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Observaciones -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Observaciones / Asignatura o Taller
                        </label>
                        <textarea
                            wire:model="observaciones"
                            rows="2"
                            placeholder="Ej. Taller de Redes - Sección 004D, uso en laboratorio..."
                            class="w-full text-sm border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                        ></textarea>
                    </div>
                </div>

                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('mostrarModalCrear', false)"
                        class="px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="registrarSolicitud"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 rounded-lg shadow-sm transition"
                    >
                        <span wire:loading.remove wire:target="registrarSolicitud">Enviar Solicitud</span>
                        <span wire:loading wire:target="registrarSolicitud">Enviando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: Detalle de Solicitud y Trazabilidad -->
    @if($mostrarModalDetalle && $solicitudDetalleId)
        @php
            $solDetalle = SolicitudMaterial::with(['detalles.insumo', 'detalles.equipo', 'entregas.devoluciones', 'entregas.entregador', 'revisor'])
                ->find($solicitudDetalleId);
        @endphp
        @if($solDetalle)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden">
                    <div class="px-5 py-4 bg-gray-900 text-white flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold">Solicitud #SOL-{{ str_pad($solDetalle->id, 5, '0', STR_PAD_LEFT) }}</h3>
                            <p class="text-xs text-gray-300">Fecha Requerida: {{ $solDetalle->fecha_requerida->format('d/m/Y') }}</p>
                        </div>
                        <button wire:click="$set('mostrarModalDetalle', false)" class="text-white/80 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
                        <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg">
                            <div>
                                <span class="text-gray-500 font-medium">Estado:</span>
                                <span class="font-bold text-gray-800 ml-1">{{ strtoupper($solDetalle->estado) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 font-medium">Revisada Por:</span>
                                <span class="text-gray-800 ml-1">{{ $solDetalle->revisor->name ?? 'En espera de revisión' }}</span>
                            </div>
                        </div>

                        <!-- Tabla de ítems -->
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2">Ítems Solicitados y Estado de Despacho</h4>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <table class="w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 text-gray-600">
                                        <tr>
                                            <th class="py-2 px-3 text-left font-semibold">Material / Equipo</th>
                                            <th class="py-2 px-3 text-center font-semibold">Pedido</th>
                                            <th class="py-2 px-3 text-center font-semibold">Entregado</th>
                                            <th class="py-2 px-3 text-center font-semibold">Devuelto</th>
                                            <th class="py-2 px-3 text-center font-semibold">Estado Devolución</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @foreach($solDetalle->detalles as $det)
                                            <tr>
                                                <td class="py-2 px-3 text-gray-800 font-medium">
                                                    {{ $det->equipo->nombre ?? $det->insumo->nombre }}
                                                </td>
                                                <td class="py-2 px-3 text-center">{{ (float)$det->cantidad_solicitada }}</td>
                                                <td class="py-2 px-3 text-center font-semibold {{ (float)$det->cantidad_entregada > 0 ? 'text-blue-600' : 'text-gray-400' }}">
                                                    {{ (float)$det->cantidad_entregada }}
                                                </td>
                                                <td class="py-2 px-3 text-center font-semibold {{ (float)$det->cantidad_devuelta > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                                                    {{ (float)$det->cantidad_devuelta }}
                                                </td>
                                                <td class="py-2 px-3 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold
                                                        {{ match($det->estado_devolucion) {
                                                            'no_aplica' => 'bg-gray-100 text-gray-600',
                                                            'pendiente' => 'bg-purple-100 text-purple-700',
                                                            'parcial' => 'bg-amber-100 text-amber-700',
                                                            'completa' => 'bg-emerald-100 text-emerald-700',
                                                            'con_danos' => 'bg-red-100 text-red-700',
                                                            default => 'bg-gray-100 text-gray-600',
                                                        } }}">
                                                        {{ $det->estado_devolucion }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Trazabilidad de Entregas -->
                        @if($solDetalle->entregas->isNotEmpty())
                            <div>
                                <h4 class="font-bold text-gray-800 mb-2">Actas de Entrega en Ventanilla</h4>
                                <div class="space-y-2">
                                    @foreach($solDetalle->entregas as $ent)
                                        <div class="p-3 bg-purple-50/60 border border-purple-200 rounded-lg">
                                            <div class="flex items-center justify-between text-gray-800 font-semibold">
                                                <span>Receptor: {{ $ent->receptor_nombre }} ({{ ucfirst($ent->receptor_tipo) }})</span>
                                                <span class="text-gray-500 font-normal">{{ $ent->fecha_entrega->format('d/m/Y H:i') }}</span>
                                            </div>
                                            <div class="text-[11px] text-gray-600 mt-0.5">
                                                Despachado por: {{ $ent->entregador->name }}
                                            </div>
                                            @if($ent->devoluciones->isNotEmpty())
                                                <div class="mt-2 pt-2 border-t border-purple-100 text-[11px]">
                                                    <span class="font-semibold text-purple-900">Devoluciones asociadas:</span>
                                                    <ul class="list-disc list-inside mt-0.5 space-y-0.5 text-gray-700">
                                                        @foreach($ent->devoluciones as $dev)
                                                            <li>
                                                                {{ $dev->fecha_devolucion->format('d/m/Y H:i') }} &bull;
                                                                Conforme: <strong class="{{ $dev->conforme ? 'text-emerald-700' : 'text-red-700' }}">{{ $dev->conforme ? 'Sí (Buen Estado)' : 'No (Con Daños)' }}</strong>
                                                                @if($dev->observaciones)
                                                                    <span class="text-gray-500"> - "{{ $dev->observaciones }}"</span>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                        <button
                            wire:click="$set('mostrarModalDetalle', false)"
                            class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
