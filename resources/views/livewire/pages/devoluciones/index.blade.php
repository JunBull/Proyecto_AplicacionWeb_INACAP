<?php

use App\Exceptions\OperacionDuplicadaException;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Services\PrestamoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $busqueda = '';
    public string $escaneoCodigo = '';
    public ?string $mensajeEscaneo = null;

    // Modal de Devolución
    public bool $mostrarModalDevolucion = false;
    public ?int $entregaDevolucionId = null;
    public array $itemsDevolucionForm = [];
    public string $observacionesDevolucion = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Entrega::class);
    }

    public function entregasActivas()
    {
        $b = trim($this->busqueda);

        $query = Entrega::with([
            'solicitud.solicitante',
            'solicitud.detalles.insumo',
            'solicitud.detalles.equipo',
            'entregador',
            'devoluciones',
        ])
        ->whereHas('solicitud', function ($q) {
            $q->where('estado', 'entregada_abierta');
        })
        ->orderByDesc('id');

        if ($b !== '') {
            $term = mb_strtolower($b);
            $t = '%' . $term . '%';
            $query->where(function ($q) use ($t, $term) {
                if (is_numeric($term)) {
                    $q->where('id', (int) $term);
                }
                $q->orWhereRaw('LOWER(receptor_nombre) LIKE ?', [$t])
                  ->orWhereRaw('LOWER(receptor_credencial_rut) LIKE ?', [$t])
                  ->orWhereHas('solicitud.solicitante', fn ($u) => $u->whereRaw('LOWER(nombre) LIKE ?', [$t])->orWhereRaw('LOWER(email) LIKE ?', [$t]))
                  ->orWhereHas('solicitud.detalles.equipo', fn ($eq) => $eq->whereRaw('LOWER(nombre) LIKE ?', [$t])->orWhereRaw('LOWER(codigo_barras) LIKE ?', [$t]))
                  ->orWhereHas('solicitud.detalles.insumo', fn ($ins) => $ins->whereRaw('LOWER(nombre) LIKE ?', [$t])->orWhereRaw('LOWER(codigo_barras) LIKE ?', [$t]));
            });
        }

        return $query->paginate(15);
    }

    public function escanearRapido(): void
    {
        $codigo = trim($this->escaneoCodigo);
        if ($codigo === '') {
            return;
        }

        // Buscar si corresponde a un equipo prestado
        $equipo = Equipo::where('codigo_barras', $codigo)->first();
        if ($equipo) {
            $entrega = Entrega::whereHas('solicitud.detalles', fn ($q) => $q->where('equipo_id', $equipo->id)->whereColumn('cantidad_devuelta', '<', 'cantidad_entregada'))
                ->whereHas('solicitud', fn ($q) => $q->where('estado', 'entregada_abierta'))
                ->latest('id')
                ->first();

            if ($entrega) {
                $this->escaneoCodigo = '';
                $this->mensajeEscaneo = null;
                $this->abrirModalDevolucion($entrega->id);
                return;
            }

            $this->mensajeEscaneo = "El equipo '{$equipo->nombre}' ({$codigo}) no tiene un préstamo pendiente de devolución.";
            $this->escaneoCodigo = '';
            return;
        }

        $this->busqueda = $codigo;
        $this->escaneoCodigo = '';
    }

    public function abrirModalDevolucion(int $entregaId): void
    {
        $this->entregaDevolucionId = $entregaId;
        $entrega = Entrega::with(['solicitud.detalles.insumo', 'solicitud.detalles.equipo'])->findOrFail($entregaId);

        $this->itemsDevolucionForm = [];
        $this->observacionesDevolucion = '';

        foreach ($entrega->solicitud->detalles as $det) {
            $pendiente = (float) $det->cantidad_entregada - (float) $det->cantidad_devuelta;
            if ($pendiente > 0 && ($det->equipo_id !== null || ($det->insumo && $det->insumo->es_retornable))) {
                $this->itemsDevolucionForm[$det->id] = [
                    'cantidad' => $pendiente,
                    'conforme' => true,
                    'descripcion_dano' => '',
                    'max' => $pendiente,
                    'nombre' => $det->equipo ? $det->equipo->nombre . ' (' . $det->equipo->codigo_barras . ')' : $det->insumo->nombre,
                    'es_equipo' => $det->equipo_id !== null,
                ];
            }
        }

        $this->mostrarModalDevolucion = true;
    }

    public function confirmarDevolucion(PrestamoService $prestamoService): void
    {
        if (! $this->entregaDevolucionId) {
            return;
        }

        $entrega = Entrega::findOrFail($this->entregaDevolucionId);
        Gate::authorize('procesarDevolucion', $entrega);

        $itemsAProcesar = [];
        foreach ($this->itemsDevolucionForm as $detalleId => $datos) {
            if ((float) ($datos['cantidad'] ?? 0) > 0) {
                $itemsAProcesar[] = [
                    'detalle_id' => (int) $detalleId,
                    'cantidad' => (float) $datos['cantidad'],
                    'conforme' => (bool) $datos['conforme'],
                    'descripcion_dano' => $datos['descripcion_dano'] ?? null,
                ];
            }
        }

        if (empty($itemsAProcesar)) {
            session()->flash('error', 'Debe seleccionar al menos un ítem con cantidad mayor a 0 para devolver.');
            return;
        }

        try {
            $prestamoService->registrarDevolucion(
                $entrega,
                Auth::user(),
                $itemsAProcesar,
                $this->observacionesDevolucion
            );

            session()->flash('mensaje', '¡Devolución procesada exitosamente! Se actualizaron las unidades y el estado de la solicitud.');
            $this->mostrarModalDevolucion = false;
        } catch (OperacionDuplicadaException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Error al procesar devolución: ' . $e->getMessage());
        }
    }
}; ?>

<div class="py-6 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <!-- Encabezado Limpio (Ponytail: Simplicidad Activa) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-7 h-7 text-red-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
                Devoluciones
            </h1>
        </div>

        <!-- Escáner / Búsqueda Rápida por Código de Barras -->
        <div class="w-full sm:w-80">
            <form wire:submit.prevent="escanearRapido" class="flex gap-2">
                <div class="relative flex-1">
                    <input
                        type="text"
                        wire:model="escaneoCodigo"
                        placeholder="Escanear código o RUT..."
                        class="w-full text-xs py-2 pl-9 pr-2 border-slate-300 rounded-xl focus:ring-red-600 focus:border-red-600 shadow-2xs font-mono"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                </div>
                <button type="submit" class="px-3 py-2 bg-red-700 hover:bg-red-800 text-white text-xs font-bold rounded-xl shadow-2xs transition">
                    Buscar
                </button>
            </form>
        </div>
    </div>

    @if($mensajeEscaneo)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs flex items-center justify-between">
            <span>{{ $mensajeEscaneo }}</span>
            <button wire:click="$set('mensajeEscaneo', null)" class="text-amber-600 font-bold ml-2">&times;</button>
        </div>
    @endif

    <!-- Alertas Flash -->
    @if(session()->has('mensaje'))
        <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800 text-xs flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <span class="font-semibold">{{ session('mensaje') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs flex items-center gap-2">
            <svg class="w-5 h-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <span class="font-semibold">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Listado de Préstamos en Espera de Devolución -->
    @php $entregasPaginadas = $this->entregasActivas(); @endphp

    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Préstamos Activos Esperando Devolución</h2>
                <span class="text-xs text-slate-500 font-normal">Equipos e insumos retornables actualmente en poder de docentes o estudiantes.</span>
            </div>
            <div class="w-full sm:w-64">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Filtrar por persona o material..."
                    class="w-full text-xs py-1.5 px-3 border-slate-200 rounded-lg focus:ring-red-500 focus:border-red-500 bg-slate-50/50"
                />
            </div>
        </div>

        @if($entregasPaginadas->isEmpty())
            <div class="text-center py-12 text-slate-400 text-sm">
                No hay préstamos con ítems pendientes de devolución en este momento.
            </div>
        @else
            <div class="space-y-4">
                @foreach($entregasPaginadas as $ent)
                    <div class="border border-slate-200 rounded-xl p-4 hover:border-slate-300 transition bg-white shadow-2xs">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-900 text-sm">#ENT-{{ str_pad($ent->id, 5, '0', STR_PAD_LEFT) }}</span>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-bold {{ $ent->receptor_tipo === 'estudiante' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $ent->receptor_nombre }} ({{ ucfirst($ent->receptor_tipo) }})
                                    </span>
                                    @if($ent->receptor_credencial_rut)
                                        <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono font-medium">
                                            RUT: {{ $ent->receptor_credencial_rut }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                                    <span>Entrega: <strong class="text-slate-700">{{ $ent->fecha_entrega->format('d/m/Y H:i') }}</strong></span>
                                    @if($ent->hora_devolucion_acordada)
                                        <span>&bull; Límite Acordado: <strong class="text-amber-800 font-bold">{{ \Carbon\Carbon::parse($ent->hora_devolucion_acordada)->format('H:i') }} hrs</strong></span>
                                    @endif
                                    <span>&bull; Despachado por: <strong class="text-slate-700">{{ $ent->entregador->name }}</strong></span>
                                    <span>&bull; Solicitud: <strong class="text-slate-700">#SOL-{{ str_pad($ent->solicitud_id, 5, '0', STR_PAD_LEFT) }}</strong></span>
                                </div>
                            </div>

                            <button
                                wire:click="abrirModalDevolucion({{ $ent->id }})"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-2xs flex items-center justify-center gap-1.5 shrink-0"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <span>Registrar Devolución</span>
                            </button>
                        </div>

                        <!-- Ítems Retornables -->
                        <div class="mt-3">
                            <ul class="text-xs space-y-1.5 text-slate-700">
                                @foreach($ent->solicitud->detalles as $det)
                                    @if($det->equipo_id !== null || ($det->insumo && $det->insumo->es_retornable))
                                        @php
                                            $entregada = (float)$det->cantidad_entregada;
                                            $devuelta = (float)$det->cantidad_devuelta;
                                            $pendiente = $entregada - $devuelta;
                                        @endphp
                                        <li class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-slate-900">{{ $det->equipo->nombre ?? $det->insumo->nombre }}</span>
                                                <span class="text-slate-400 font-mono text-[11px]">({{ $det->equipo->codigo_barras ?? $det->insumo->codigo_barras }})</span>
                                                <span class="px-1.5 py-0.2 rounded text-[10px] {{ $det->equipo ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                                    {{ $det->equipo ? 'Equipo' : 'Insumo Retornable' }}
                                                </span>
                                            </div>
                                            <div class="text-right text-xs">
                                                <span class="text-slate-500">Entregado: {{ $entregada }} &bull; Devuelto: {{ $devuelta }}</span>
                                                <span class="ml-2 font-bold {{ $pendiente > 0 ? 'text-indigo-700' : 'text-emerald-600' }}">
                                                    Pendiente: {{ $pendiente }}
                                                </span>
                                            </div>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5">
                {{ $entregasPaginadas->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL: Inspección y Registro de Devolución -->
    @if($mostrarModalDevolucion && $entregaDevolucionId)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden">
                <div class="px-5 py-4 bg-indigo-700 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Inspección y Devolución de Materiales
                    </h3>
                    <button wire:click="$set('mostrarModalDevolucion', false)" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto text-xs">
                    <p class="text-slate-500">
                        Indique las cantidades devueltas y el estado físico de cada ítem. Si presenta daños, el equipo se apartará automáticamente a estado "No Disponible" y se abrirá una incidencia técnica.
                    </p>

                    <div class="space-y-3">
                        @foreach($itemsDevolucionForm as $detId => $item)
                            <div class="p-3 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                                <div class="flex items-center justify-between font-semibold text-slate-900">
                                    <span>{{ $item['nombre'] }}</span>
                                    <span class="text-slate-500 text-[11px]">Máx pendiente: {{ $item['max'] }}</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-700 mb-0.5">Cantidad a Devolver:</label>
                                        @if($item['es_equipo'])
                                            <input
                                                type="number"
                                                value="1"
                                                disabled
                                                class="w-full text-xs border-slate-300 rounded-lg bg-slate-100 text-slate-500 cursor-not-allowed py-1"
                                            />
                                        @else
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max="{{ $item['max'] }}"
                                                wire:model="itemsDevolucionForm.{{ $detId }}.cantidad"
                                                class="w-full text-xs border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 py-1"
                                            />
                                        @endif
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-700 mb-0.5">Estado Físico:</label>
                                        <select
                                            wire:model.live="itemsDevolucionForm.{{ $detId }}.conforme"
                                            class="w-full text-xs border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 py-1"
                                        >
                                            <option value="1">Conforme (Buen estado / Operativo)</option>
                                            <option value="0">Con Daños / Desperfectos</option>
                                        </select>
                                    </div>
                                </div>

                                @if(! $item['conforme'])
                                    <div class="pt-1">
                                        <label class="block text-[11px] font-semibold text-red-700 mb-0.5">Descripción de la anomalía o daño (Obligatorio):</label>
                                        <textarea
                                            wire:model="itemsDevolucionForm.{{ $detId }}.descripcion_dano"
                                            rows="2"
                                            placeholder="Detalle el estado del material, daño físico o mal funcionamiento..."
                                            class="w-full text-xs border-red-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                        ></textarea>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Observaciones Generales de la Devolución (Opcional):</label>
                        <textarea
                            wire:model="observacionesDevolucion"
                            rows="2"
                            placeholder="Notas adicionales sobre la entrega o el estado general..."
                            class="w-full text-xs border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('mostrarModalDevolucion', false)"
                        class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-lg transition"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="confirmarDevolucion"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg shadow-sm transition flex items-center gap-1.5"
                    >
                        <span wire:loading.remove wire:target="confirmarDevolucion">Confirmar Devolución e Inspección</span>
                        <span wire:loading wire:target="confirmarDevolucion">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>