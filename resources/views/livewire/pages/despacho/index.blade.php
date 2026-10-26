<?php

use App\Exceptions\EquipoNoDisponibleException;
use App\Exceptions\OperacionDuplicadaException;
use App\Exceptions\StockInsuficienteException;
use App\Models\DetalleSolicitud;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\SolicitudMaterial;
use App\Services\BarcodeService;
use App\Services\PrestamoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $pestanaActiva = 'solicitudes'; // solicitudes, activos, historial
    public string $filtroEstado = 'todas'; // todas, pendientes, aprobadas, entregadas_abiertas, cerradas
    public string $busquedaRapida = '';
    public string $escaneoCodigoBarras = '';
    public ?string $resultadoEscaneo = null;

    // Modal de Entrega / Despacho
    public bool $mostrarModalEntrega = false;
    public ?int $solicitudEntregaId = null;
    public string $receptorNombre = '';
    public string $receptorTipo = 'docente'; // docente o estudiante
    public array $detallesSeleccionadosIds = [];

    // Modal de Devolución
    public bool $mostrarModalDevolucion = false;
    public ?int $entregaDevolucionId = null;
    public array $itemsDevolucionForm = []; // [detalle_id => ['cantidad' => float, 'conforme' => bool, 'descripcion_dano' => string]]
    public string $observacionesDevolucion = '';

    // Modal de Rechazo
    public bool $mostrarModalRechazo = false;
    public ?int $solicitudRechazoId = null;
    public string $motivoRechazo = '';

    public function escanearCodigo(): void
    {
        $codigo = trim($this->escaneoCodigoBarras);
        if ($codigo === '') {
            return;
        }

        // Buscar primero como equipo
        $equipo = Equipo::with('ubicacion')->where('codigo_barras', $codigo)->first();
        if ($equipo) {
            $this->resultadoEscaneo = "Equipo encontrado: {$equipo->nombre} (Estado: {$equipo->estado}) en {$equipo->ubicacion->nombre}.";
            $this->escaneoCodigoBarras = '';
            return;
        }

        // Buscar como insumo
        $insumo = Insumo::with('ubicacion')->where('codigo_barras', $codigo)->first();
        if ($insumo) {
            $tipo = $insumo->es_retornable ? 'Retornable' : 'Consumible';
            $this->resultadoEscaneo = "Insumo encontrado: {$insumo->nombre} (Stock: {$insumo->stock_actual} {$insumo->unidad}, {$tipo}) en {$insumo->ubicacion->nombre}.";
            $this->escaneoCodigoBarras = '';
            return;
        }

        $this->resultadoEscaneo = "No se encontró ningún equipo o insumo con el código '{$codigo}'.";
    }

    public function aprobar(int $solicitudId, PrestamoService $prestamoService): void
    {
        $solicitud = SolicitudMaterial::findOrFail($solicitudId);
        try {
            $prestamoService->aprobarSolicitud($solicitud, Auth::user());
            session()->flash('mensaje', "Solicitud #SOL-{$solicitudId} aprobada correctamente.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function abrirModalRechazo(int $solicitudId): void
    {
        $this->solicitudRechazoId = $solicitudId;
        $this->motivoRechazo = '';
        $this->mostrarModalRechazo = true;
    }

    public function confirmarRechazo(PrestamoService $prestamoService): void
    {
        if (! $this->solicitudRechazoId) {
            return;
        }

        $solicitud = SolicitudMaterial::findOrFail($this->solicitudRechazoId);
        try {
            $prestamoService->rechazarSolicitud($solicitud, Auth::user(), $this->motivoRechazo);
            session()->flash('mensaje', "Solicitud #SOL-{$this->solicitudRechazoId} rechazada.");
            $this->mostrarModalRechazo = false;
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function marcarSinDisponibilidad(int $solicitudId, PrestamoService $prestamoService): void
    {
        $solicitud = SolicitudMaterial::findOrFail($solicitudId);
        try {
            $prestamoService->marcarSinDisponibilidad($solicitud, Auth::user(), 'Sin disponibilidad física en pañol al momento de la revisión.');
            session()->flash('mensaje', "Solicitud #SOL-{$solicitudId} marcada como sin disponibilidad.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function abrirModalEntrega(int $solicitudId): void
    {
        $this->resetValidation();
        $this->solicitudEntregaId = $solicitudId;
        $solicitud = SolicitudMaterial::with(['solicitante', 'detalles.insumo', 'detalles.equipo'])->findOrFail($solicitudId);

        $this->receptorNombre = $solicitud->solicitante->name;
        $this->receptorTipo = $solicitud->solicitante->rol === 'docente' ? 'docente' : 'estudiante';
        
        // Seleccionar por defecto todos los detalles con cantidad pendiente
        $this->detallesSeleccionadosIds = $solicitud->detalles
            ->filter(fn ($d) => (float)$d->cantidad_entregada < (float)$d->cantidad_solicitada)
            ->pluck('id')
            ->map(fn ($id) => (int)$id)
            ->all();

        $this->mostrarModalEntrega = true;
    }

    public function confirmarEntrega(PrestamoService $prestamoService): void
    {
        $this->validate([
            'receptorNombre' => 'required|string|min:3',
            'receptorTipo' => 'required|in:docente,estudiante',
            'detallesSeleccionadosIds' => 'required|array|min:1',
        ], [
            'receptorNombre.required' => 'El nombre del receptor es obligatorio.',
            'receptorTipo.in' => 'El tipo de receptor debe ser docente o estudiante.',
            'detallesSeleccionadosIds.min' => 'Debe seleccionar al menos un ítem a despachar.',
        ]);

        $solicitud = SolicitudMaterial::findOrFail($this->solicitudEntregaId);

        try {
            $prestamoService->entregarMateriales(
                $solicitud,
                Auth::user(),
                $this->receptorNombre,
                $this->receptorTipo,
                $this->detallesSeleccionadosIds
            );

            session()->flash('mensaje', "¡Despacho confirmado exitosamente! Entrega registrada y stock actualizado en kardex.");
            $this->mostrarModalEntrega = false;
        } catch (EquipoNoDisponibleException|StockInsuficienteException|OperacionDuplicadaException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Ocurrió un error al procesar la entrega: ' . $e->getMessage());
        }
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

    public function solicitudes()
    {
        $query = SolicitudMaterial::with(['solicitante', 'revisor', 'detalles.insumo', 'detalles.equipo', 'entregas'])
            ->orderByDesc('id');

        if ($this->filtroEstado === 'pendientes') {
            $query->where('estado', 'pendiente');
        } elseif ($this->filtroEstado === 'aprobadas') {
            $query->where('estado', 'aprobada');
        } elseif ($this->filtroEstado === 'entregadas_abiertas') {
            $query->where('estado', 'entregada_abierta');
        } elseif ($this->filtroEstado === 'cerradas') {
            $query->where('estado', 'cerrada');
        }

        if (trim($this->busquedaRapida) !== '') {
            $t = '%' . trim($this->busquedaRapida) . '%';
            $query->where(function ($q) use ($t) {
                $q->whereHas('solicitante', fn ($u) => $u->where('name', 'LIKE', $t)->orWhere('email', 'LIKE', $t))
                  ->orWhere('id', 'LIKE', $t)
                  ->orWhere('observaciones', 'LIKE', $t);
            });
        }

        return $query->paginate(15);
    }

    public function entregasActivas()
    {
        return Entrega::with(['solicitud.solicitante', 'solicitud.detalles.insumo', 'solicitud.detalles.equipo', 'entregador', 'devoluciones'])
            ->whereHas('solicitud', function ($q) {
                $q->where('estado', 'entregada_abierta');
            })
            ->orderByDesc('id')
            ->get();
    }

    public function historialEntregas()
    {
        return Entrega::with(['solicitud.solicitante', 'entregador', 'devoluciones.recibidor'])
            ->orderByDesc('id')
            ->paginate(15);
    }
}; ?>

<div class="py-6 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <!-- Encabezado del Módulo Pañolero -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-7 h-7 text-red-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
                Gestión de Mesón: Despacho y Devoluciones
            </h1>
            <p class="text-sm text-gray-500 mt-1">Revisión de solicitudes anticipadas, control de entregas por ventanilla e inspección física de devoluciones.</p>
        </div>

        <!-- Escáner Rápido de Código de Barras (Hardware 1D / Teclado) -->
        <div class="w-full md:w-80 bg-white p-2.5 rounded-xl border border-gray-200 shadow-xs">
            <form wire:submit.prevent="escanearCodigo" class="flex gap-1.5">
                <div class="relative flex-1">
                    <input
                        type="text"
                        wire:model="escaneoCodigoBarras"
                        placeholder="Escanear código de barras..."
                        class="w-full text-xs font-mono py-1.5 pl-8 pr-2 border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                </div>
                <button type="submit" class="px-3 py-1.5 bg-gray-900 text-white text-xs font-semibold rounded-lg hover:bg-black transition">
                    Verificar
                </button>
            </form>
            @if($resultadoEscaneo)
                <div class="mt-2 text-[11px] p-2 bg-blue-50 text-blue-800 rounded border border-blue-200 flex items-center justify-between">
                    <span>{{ $resultadoEscaneo }}</span>
                    <button wire:click="$set('resultadoEscaneo', null)" class="text-blue-600 font-bold ml-2">&times;</button>
                </div>
            @endif
        </div>
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

    <!-- Pestañas Principales del Panel -->
    <div class="flex border-b border-gray-200 mb-6 bg-white rounded-t-xl px-4 pt-2">
        <button
            wire:click="$set('pestanaActiva', 'solicitudes')"
            class="py-3 px-4 text-sm font-semibold border-b-2 flex items-center gap-2 transition
                {{ $pestanaActiva === 'solicitudes' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Cola de Solicitudes y Despacho
        </button>
        <button
            wire:click="$set('pestanaActiva', 'activos')"
            class="py-3 px-4 text-sm font-semibold border-b-2 flex items-center gap-2 transition
                {{ $pestanaActiva === 'activos' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Préstamos Activos (Esperando Devolución)
        </button>
        <button
            wire:click="$set('pestanaActiva', 'historial')"
            class="py-3 px-4 text-sm font-semibold border-b-2 flex items-center gap-2 transition
                {{ $pestanaActiva === 'historial' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Trazabilidad Histórica
        </button>
    </div>

    <!-- PESTAÑA 1: COLA DE SOLICITUDES Y DESPACHO -->
    @if($pestanaActiva === 'solicitudes')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
            <!-- Barra de Filtros y Búsqueda -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 font-medium">Estado:</span>
                    <select
                        wire:model.live="filtroEstado"
                        class="text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 py-1.5"
                    >
                        <option value="todas">Todas las Solicitudes</option>
                        <option value="pendientes">Pendientes de Revisión</option>
                        <option value="aprobadas">Aprobadas para Entrega</option>
                        <option value="entregadas_abiertas">Préstamo Abierto</option>
                        <option value="cerradas">Cerradas</option>
                    </select>
                </div>
                <div class="w-full sm:w-64">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="busquedaRapida"
                        placeholder="Buscar por solicitante, folio..."
                        class="w-full text-xs py-1.5 border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                    />
                </div>
            </div>

            @php $solicitudesPaginadas = $this->solicitudes(); @endphp

            @if($solicitudesPaginadas->isEmpty())
                <div class="text-center py-12 text-gray-400 text-sm">
                    No se encontraron solicitudes con los filtros aplicados.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($solicitudesPaginadas as $sol)
                        <div class="border border-gray-200 rounded-xl p-4 hover:border-gray-300 transition bg-white shadow-xs">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900 text-base">#SOL-{{ str_pad($sol->id, 5, '0', STR_PAD_LEFT) }}</span>
                                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full
                                            {{ match($sol->estado) {
                                                'pendiente' => 'bg-amber-100 text-amber-800',
                                                'aprobada' => 'bg-blue-100 text-blue-800',
                                                'entregada_abierta' => 'bg-purple-100 text-purple-800',
                                                'cerrada' => 'bg-emerald-100 text-emerald-800',
                                                'rechazada' => 'bg-red-100 text-red-800',
                                                'sin_disponibilidad' => 'bg-gray-100 text-gray-700',
                                                default => 'bg-gray-100 text-gray-800',
                                            } }}">
                                            {{ strtoupper($sol->estado) }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Solicitante: <strong class="text-gray-800">{{ $sol->solicitante->name }}</strong> ({{ $sol->solicitante->email }}) &bull;
                                        Fecha Solicitud: {{ $sol->fecha_solicitud->format('d/m/Y H:i') }} &bull;
                                        Requerida: <strong class="text-gray-800">{{ $sol->fecha_requerida->format('d/m/Y') }}</strong>
                                    </div>
                                </div>

                                <!-- Botonera de Acciones según BPMN Proceso 3 -->
                                <div class="flex items-center gap-2">
                                    @if($sol->estado === 'pendiente')
                                        <button
                                            wire:click="aprobar({{ $sol->id }})"
                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition"
                                        >
                                            Aprobar
                                        </button>
                                        <button
                                            wire:click="marcarSinDisponibilidad({{ $sol->id }})"
                                            class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium transition"
                                            title="Marcar falta de existencias físicas"
                                        >
                                            Sin Disponibilidad
                                        </button>
                                        <button
                                            wire:click="abrirModalRechazo({{ $sol->id }})"
                                            class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-medium transition"
                                        >
                                            Rechazar
                                        </button>
                                    @endif

                                    @if(in_array($sol->estado, ['aprobada', 'pendiente', 'entregada_abierta']))
                                        @php
                                            $tienePendientesEntrega = $sol->detalles->contains(fn ($d) => (float)$d->cantidad_entregada < (float)$d->cantidad_solicitada);
                                        @endphp
                                        @if($tienePendientesEntrega)
                                            <button
                                                wire:click="abrirModalEntrega({{ $sol->id }})"
                                                class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-xs"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                Entregar en Mesón
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            <!-- Listado de ítems de la solicitud -->
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px]">
                                        <tr>
                                            <th class="py-1.5 px-3">Tipo</th>
                                            <th class="py-1.5 px-3">Ítem / Código</th>
                                            <th class="py-1.5 px-3 text-center">Solicitado</th>
                                            <th class="py-1.5 px-3 text-center">Entregado</th>
                                            <th class="py-1.5 px-3 text-center">Devuelto</th>
                                            <th class="py-1.5 px-3 text-center">Estado Devolución</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($sol->detalles as $det)
                                            <tr>
                                                <td class="py-2 px-3">
                                                    @if($det->equipo)
                                                        <span class="px-2 py-0.5 rounded text-[10px] bg-blue-100 text-blue-700 font-semibold">Equipo Retornable</span>
                                                    @elseif($det->insumo && $det->insumo->es_retornable)
                                                        <span class="px-2 py-0.5 rounded text-[10px] bg-indigo-100 text-indigo-700 font-semibold">Insumo Retornable</span>
                                                    @else
                                                        <span class="px-2 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 font-semibold">Insumo Consumible</span>
                                                    @endif
                                                </td>
                                                <td class="py-2 px-3 font-medium text-gray-800">
                                                    {{ $det->equipo->nombre ?? $det->insumo->nombre }}
                                                    <span class="text-gray-400 font-mono text-[11px]">({{ $det->equipo->codigo_barras ?? $det->insumo->codigo_barras }})</span>
                                                </td>
                                                <td class="py-2 px-3 text-center font-bold">{{ (float)$det->cantidad_solicitada }}</td>
                                                <td class="py-2 px-3 text-center font-bold {{ (float)$det->cantidad_entregada > 0 ? 'text-blue-600' : 'text-gray-400' }}">
                                                    {{ (float)$det->cantidad_entregada }}
                                                </td>
                                                <td class="py-2 px-3 text-center font-bold {{ (float)$det->cantidad_devuelta > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
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
                    @endforeach
                </div>
                <div class="mt-4">
                    {{ $solicitudesPaginadas->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- PESTAÑA 2: PRÉSTAMOS ACTIVOS (ESPERANDO DEVOLUCIÓN) -->
    @if($pestanaActiva === 'activos')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-gray-900">Préstamos Activos con Devolución Pendiente</h2>
                <p class="text-xs text-gray-500">Materiales y equipos que se encuentran actualmente en poder de docentes o estudiantes.</p>
            </div>

            @php $activas = $this->entregasActivas(); @endphp

            @if($activas->isEmpty())
                <div class="text-center py-12 text-gray-400 text-sm">
                    No hay préstamos activos con unidades pendientes de devolución.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($activas as $ent)
                        <div class="border border-purple-200 bg-purple-50/20 rounded-xl p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-purple-100 pb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900 text-sm">Entrega #ENT-{{ str_pad($ent->id, 5, '0', STR_PAD_LEFT) }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-semibold">
                                            Receptor: {{ $ent->receptor_nombre }} ({{ ucfirst($ent->receptor_tipo) }})
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Fecha de Entrega: {{ $ent->fecha_entrega->format('d/m/Y H:i') }} &bull;
                                        Despachado por: {{ $ent->entregador->name }} &bull;
                                        Solicitud: #SOL-{{ str_pad($ent->solicitud_id, 5, '0', STR_PAD_LEFT) }}
                                    </div>
                                </div>
                                <button
                                    wire:click="abrirModalDevolucion({{ $ent->id }})"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition shadow-xs flex items-center gap-1.5"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Registrar Devolución
                                </button>
                            </div>

                            <!-- Listado de ítems entregados y pendientes -->
                            <div class="mt-3">
                                <h4 class="text-xs font-bold text-gray-700 mb-1.5">Ítems Vinculados al Préstamo:</h4>
                                <ul class="text-xs space-y-1 text-gray-600">
                                    @foreach($ent->solicitud->detalles as $det)
                                        @if($det->equipo_id !== null || ($det->insumo && $det->insumo->es_retornable))
                                            <li class="flex items-center justify-between p-2 rounded bg-white border border-gray-100">
                                                <div>
                                                    <span class="font-medium text-gray-900">{{ $det->equipo->nombre ?? $det->insumo->nombre }}</span>
                                                    <span class="text-gray-400 font-mono text-[11px]">({{ $det->equipo->codigo_barras ?? $det->insumo->codigo_barras }})</span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-gray-500">Entregado: {{ (float)$det->cantidad_entregada }} &bull; Devuelto: {{ (float)$det->cantidad_devuelta }}</span>
                                                    <span class="ml-2 font-bold text-purple-700">Pendiente: {{ (float)$det->cantidad_entregada - (float)$det->cantidad_devuelta }}</span>
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- PESTAÑA 3: TRAZABILIDAD HISTÓRICA -->
    @if($pestanaActiva === 'historial')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-gray-900">Historial de Entregas y Devoluciones (Auditoría KPI-03)</h2>
                <p class="text-xs text-gray-500">Registro inmutable de movimientos, receptores y encargados de pañol.</p>
            </div>

            @php $historial = $this->historialEntregas(); @endphp

            @if($historial->isEmpty())
                <div class="text-center py-12 text-gray-400 text-sm">
                    No hay registros históricos de entregas.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-600 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-2 px-3">Folio Entrega</th>
                                <th class="py-2 px-3">Fecha y Hora</th>
                                <th class="py-2 px-3">Receptor</th>
                                <th class="py-2 px-3">Tipo</th>
                                <th class="py-2 px-3">Despachador</th>
                                <th class="py-2 px-3">Devoluciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($historial as $h)
                                <tr>
                                    <td class="py-2 px-3 font-bold text-gray-900">#ENT-{{ str_pad($h->id, 5, '0', STR_PAD_LEFT) }}</td>
                                    <td class="py-2 px-3 text-gray-600">{{ $h->fecha_entrega->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 px-3 font-semibold text-gray-800">{{ $h->receptor_nombre }}</td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $h->receptor_tipo === 'docente' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ ucfirst($h->receptor_tipo) }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-gray-600">{{ $h->entregador->name }}</td>
                                    <td class="py-2 px-3">
                                        @if($h->devoluciones->isEmpty())
                                            <span class="text-gray-400 italic">Sin devoluciones</span>
                                        @else
                                            <span class="text-emerald-600 font-semibold">{{ $h->devoluciones->count() }} registrada(s)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $historial->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- MODAL: Confirmar Entrega en Mesón (CP-20, CP-22, PI-02, PI-03) -->
    @if($mostrarModalEntrega && $solicitudEntregaId)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden">
                <div class="px-5 py-4 bg-emerald-700 text-white flex items-center justify-between">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirmar Entrega de Materiales en Ventanilla
                    </h3>
                    <button wire:click="$set('mostrarModalEntrega', false)" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto text-xs">
                    <!-- Datos del Receptor (Regla de negocio: alumnos sin cuenta) -->
                    <div class="p-3 bg-amber-50 rounded-lg border border-amber-200">
                        <span class="font-bold text-amber-900 block mb-1">Registro de Receptor (Trazabilidad estricta):</span>
                        <p class="text-[11px] text-amber-700">
                            Los estudiantes retiran material en mesón sin necesidad de cuentas de acceso en el sistema. Ingrese nombre completo y RUT/identificación.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Nombre Completo del Receptor <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                wire:model="receptorNombre"
                                placeholder="Ej. Juan Pérez González"
                                class="w-full text-xs border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            @error('receptorNombre')
                                <p class="text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Tipo de Receptor <span class="text-red-500">*</span></label>
                            <select
                                wire:model="receptorTipo"
                                class="w-full text-xs border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="docente">Docente Institucional</option>
                                <option value="estudiante">Estudiante en Práctica / Taller</option>
                            </select>
                            @error('receptorTipo')
                                <p class="text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Selección de Ítems a Entregar -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1.5">Ítems a Entregar en este Despacho:</label>
                        @php
                            $solicitudModal = SolicitudMaterial::with('detalles.insumo', 'detalles.equipo')->find($solicitudEntregaId);
                        @endphp
                        @if($solicitudModal)
                            <div class="space-y-2 border border-gray-200 rounded-lg p-3 bg-gray-50/50">
                                @foreach($solicitudModal->detalles as $det)
                                    @php
                                        $pendiente = (float)$det->cantidad_solicitada - (float)$det->cantidad_entregada;
                                    @endphp
                                    @if($pendiente > 0)
                                        <label class="flex items-center justify-between p-2 rounded bg-white border border-gray-200 cursor-pointer hover:bg-gray-50">
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    wire:model="detallesSeleccionadosIds"
                                                    value="{{ $det->id }}"
                                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                />
                                                <div>
                                                    <span class="font-medium text-gray-900">{{ $det->equipo->nombre ?? $det->insumo->nombre }}</span>
                                                    <span class="text-gray-400 font-mono text-[10px]">({{ $det->equipo->codigo_barras ?? $det->insumo->codigo_barras }})</span>
                                                    <div class="text-[10px] text-gray-500">
                                                        {{ $det->equipo ? 'Equipo Retornable' : ($det->insumo->es_retornable ? 'Insumo Retornable' : 'Insumo Consumible (Salida definitiva)') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-bold text-gray-800">{{ $pendiente }} un.</span>
                                            </div>
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('mostrarModalEntrega', false)"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 rounded-lg transition"
                    >
                        Cancelar
                    </button>
                    <!-- Protección contra doble clic / Idempotencia en cliente y servidor (CP-22) -->
                    <button
                        type="button"
                        wire:click="confirmarEntrega"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 rounded-lg shadow-sm transition flex items-center gap-1.5"
                    >
                        <span wire:loading.remove wire:target="confirmarEntrega">Confirmar Entrega y Rebajar Stock</span>
                        <span wire:loading wire:target="confirmarEntrega">Procesando Entrega...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: Registrar Devolución e Incidencias (CP-21, CP-22) -->
    @if($mostrarModalDevolucion && $entregaDevolucionId)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden">
                <div class="px-5 py-4 bg-indigo-700 text-white flex items-center justify-between">
                    <h3 class="text-base font-bold flex items-center gap-2">
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
                    <p class="text-gray-500">
                        Indique las cantidades devueltas y el estado físico de cada ítem. Si presenta daños, el equipo se apartará automáticamente a estado "No Disponible" y se abrirá una incidencia técnica (CP-21).
                    </p>

                    <div class="space-y-3">
                        @foreach($itemsDevolucionForm as $detId => $item)
                            <div class="p-3 rounded-lg border border-gray-200 bg-gray-50/50 space-y-2">
                                <div class="flex items-center justify-between font-semibold text-gray-900">
                                    <span>{{ $item['nombre'] }}</span>
                                    <span class="text-gray-500 text-[11px]">Máx pendiente: {{ $item['max'] }}</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <!-- Cantidad -->
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Cantidad a Devolver:</label>
                                        @if($item['es_equipo'])
                                            <input type="text" disabled value="1 unidad" class="w-full text-xs py-1 px-2 bg-gray-100 border-gray-300 rounded text-gray-600 font-semibold" />
                                        @else
                                            <input
                                                type="number"
                                                step="1"
                                                min="0"
                                                max="{{ $item['max'] }}"
                                                wire:model="itemsDevolucionForm.{{ $detId }}.cantidad"
                                                class="w-full text-xs py-1 px-2 border-gray-300 rounded"
                                            />
                                        @endif
                                    </div>

                                    <!-- Estado / Conformidad -->
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Estado Físico:</label>
                                        <select
                                            wire:model.live="itemsDevolucionForm.{{ $detId }}.conforme"
                                            class="w-full text-xs py-1 px-2 border-gray-300 rounded {{ ! $item['conforme'] ? 'bg-red-50 text-red-800 font-bold border-red-300' : 'bg-emerald-50 text-emerald-800 font-bold border-emerald-300' }}"
                                        >
                                            <option value="1">Buen Estado (Conforme)</option>
                                            <option value="0">Con Daños / Falla Física</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Detalle del daño si no está conforme -->
                                @if(! $item['conforme'])
                                    <div class="mt-2 p-2 bg-red-50 rounded border border-red-200">
                                        <label class="block text-[11px] font-bold text-red-800 mb-1">Descripción del Desperfecto o Daño Observado:</label>
                                        <input
                                            type="text"
                                            wire:model="itemsDevolucionForm.{{ $detId }}.descripcion_dano"
                                            placeholder="Ej. Pantalla fisurada, conector doblado, cable cortado..."
                                            class="w-full text-xs py-1 px-2 border-red-300 rounded focus:ring-red-500 focus:border-red-500 bg-white"
                                        />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <!-- Observaciones Generales -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Observaciones Generales de la Devolución:</label>
                        <textarea
                            wire:model="observacionesDevolucion"
                            rows="2"
                            placeholder="Comentarios adicionales sobre la recepción en pañol..."
                            class="w-full text-xs border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('mostrarModalDevolucion', false)"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 rounded-lg transition"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="confirmarDevolucion"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg shadow-sm transition flex items-center gap-1.5"
                    >
                        <span wire:loading.remove wire:target="confirmarDevolucion">Confirmar Devolución</span>
                        <span wire:loading wire:target="confirmarDevolucion">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: Rechazar Solicitud -->
    @if($mostrarModalRechazo && $solicitudRechazoId)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="px-5 py-4 bg-red-600 text-white flex items-center justify-between">
                    <h3 class="text-base font-bold">Rechazar Solicitud #SOL-{{ $solicitudRechazoId }}</h3>
                    <button wire:click="$set('mostrarModalRechazo', false)" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-3 text-xs">
                    <label class="block font-semibold text-gray-700">Motivo del Rechazo:</label>
                    <textarea
                        wire:model="motivoRechazo"
                        rows="3"
                        placeholder="Indique el motivo por el cual no se puede atender la solicitud..."
                        class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                    ></textarea>
                </div>
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
                    <button wire:click="$set('mostrarModalRechazo', false)" class="px-3 py-1.5 text-xs text-gray-600">Cancelar</button>
                    <button wire:click="confirmarRechazo" class="px-4 py-1.5 bg-red-600 text-white font-bold rounded-lg text-xs hover:bg-red-700">Confirmar Rechazo</button>
                </div>
            </div>
        </div>
    @endif
</div>
