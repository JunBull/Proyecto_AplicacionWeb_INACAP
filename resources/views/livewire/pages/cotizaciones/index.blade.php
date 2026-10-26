<?php

use App\Exceptions\SaldoInsuficienteException;
use App\Models\Carrera;
use App\Models\Cotizacion;
use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use App\Services\CotizacionService;
use App\Services\PresupuestoService;
use App\Services\ProveedorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    // Filtros
    public ?int $filtroCarreraId = null;

    // Modal Crear Solicitud
    public bool $mostrarModalSolicitud = false;
    public ?int $carrera_id = null;
    public string $descripcion = '';
    public array $proveedoresSeleccionados = [];

    // Modal Cargar Cotización
    public bool $mostrarModalCargarCotizacion = false;
    public ?int $solicitudIdParaCotizacion = null;
    public ?int $proveedorCotizacionId = null;
    public float|string $montoCotizacion = '';
    public string $fechaRecepcion = '';
    public $archivoCotizacion = null;

    // Modal Adjudicación
    public bool $mostrarModalAdjudicar = false;
    public ?int $cotizacionIdParaAdjudicar = null;
    public bool $excepcionAutorizada = false;
    public string $justificacionExcepcion = '';

    public function mount(): void
    {
        $this->fechaRecepcion = date('Y-m-d');
        $carrera = Carrera::first();
        if ($carrera) {
            $this->carrera_id = $carrera->id;
        }
    }

    public function solicitudes(CotizacionService $service)
    {
        return $service->listarSolicitudes($this->filtroCarreraId);
    }

    public function guardarSolicitud(CotizacionService $service): void
    {
        try {
            $solicitud = $service->crearSolicitudCotizacion([
                'carrera_id' => (int) $this->carrera_id,
                'descripcion' => $this->descripcion,
            ], Auth::user());

            if (! empty($this->proveedoresSeleccionados)) {
                $service->despacharSolicitudProveedores($solicitud, array_map('intval', $this->proveedoresSeleccionados));
            }

            $this->reset(['descripcion', 'proveedoresSeleccionados', 'mostrarModalSolicitud']);
            session()->flash('mensaje', 'Solicitud de cotización creada y correos encolados para despacho asíncrono (CP-17, SLA-05).');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function abrirCargaCotizacion(int $solicitudId): void
    {
        $this->solicitudIdParaCotizacion = $solicitudId;
        $this->mostrarModalCargarCotizacion = true;
    }

    public function guardarCotizacion(CotizacionService $service): void
    {
        try {
            $solicitud = SolicitudCotizacion::findOrFail($this->solicitudIdParaCotizacion);
            $proveedor = Proveedor::findOrFail($this->proveedorCotizacionId);

            $service->registrarCotizacion(
                $solicitud,
                $proveedor,
                (float) $this->montoCotizacion,
                $this->fechaRecepcion,
                $this->archivoCotizacion,
                Auth::user()
            );

            $this->reset([
                'solicitudIdParaCotizacion',
                'proveedorCotizacionId',
                'montoCotizacion',
                'archivoCotizacion',
                'mostrarModalCargarCotizacion',
            ]);
            $this->fechaRecepcion = date('Y-m-d');
            session()->flash('mensaje', 'Propuesta de cotización registrada y respaldada exitosamente (CP-18).');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function abrirModalAdjudicar(int $cotizacionId): void
    {
        $this->cotizacionIdParaAdjudicar = $cotizacionId;
        $this->excepcionAutorizada = false;
        $this->justificacionExcepcion = '';
        $this->mostrarModalAdjudicar = true;
    }

    public function confirmarAdjudicacion(CotizacionService $service): void
    {
        try {
            $cotizacion = Cotizacion::with(['solicitudCotizacion.carrera', 'proveedor'])->findOrFail($this->cotizacionIdParaAdjudicar);

            $service->adjudicarCotizacion(
                $cotizacion,
                Auth::user(),
                $this->excepcionAutorizada,
                $this->justificacionExcepcion
            );

            $this->reset(['mostrarModalAdjudicar', 'cotizacionIdParaAdjudicar', 'excepcionAutorizada', 'justificacionExcepcion']);
            session()->flash('mensaje', "Adjudicación confirmada para {$cotizacion->proveedor->razon_social} cumpliendo KPI-08.");
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (SaldoInsuficienteException $e) {
            $this->addError('adjudicacion', $e->getMessage());
        }
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session()->has('mensaje'))
            <div class="p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-sm rounded shadow">
                {{ session('mensaje') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Proceso 1: Cotizaciones y Adjudicaciones</h2>
                    <p class="text-sm text-gray-500">Despacho asíncrono vía colas, validación de cuota mínima y control presupuestario (CP-17, CP-18, KPI-08, SLA-05)</p>
                </div>
                <button
                    wire:click="$set('mostrarModalSolicitud', true)"
                    class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-md shadow"
                >
                    + Nueva Solicitud de Cotización
                </button>
            </div>

            <!-- Listado de Solicitudes -->
            <div class="space-y-6">
                @forelse($this->solicitudes(app(\App\Services\CotizacionService::class)) as $solicitud)
                    <div class="border border-gray-200 rounded-lg p-5 bg-gray-50 space-y-4">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b pb-3">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Solicitud #{{ $solicitud->id }}</span>
                                <h3 class="text-base font-bold text-gray-900">{{ $solicitud->carrera->nombre ?? 'Carrera' }}</h3>
                                <p class="text-xs text-gray-500">Emitida el {{ $solicitud->fecha_creacion->format('d/m/Y H:i') }} por {{ $solicitud->creador->name ?? 'Usuario' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded text-xs font-semibold
                                    @if($solicitud->estado === 'adjudicada') bg-green-100 text-green-800
                                    @elseif($solicitud->estado === 'en_evaluacion') bg-blue-100 text-blue-800
                                    @elseif($solicitud->estado === 'enviada') bg-amber-100 text-amber-800
                                    @else bg-gray-200 text-gray-700 @endif
                                ">
                                    {{ ucfirst(str_replace('_', ' ', $solicitud->estado)) }}
                                </span>
                                @if($solicitud->estado !== 'adjudicada' && $solicitud->estado !== 'cancelada')
                                    <button
                                        wire:click="abrirCargaCotizacion({{ $solicitud->id }})"
                                        class="px-3 py-1 bg-white border border-gray-300 hover:bg-gray-100 text-xs font-semibold rounded text-gray-700 shadow-sm"
                                    >
                                        + Cargar Cotización
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="text-sm text-gray-700 bg-white p-3 rounded border border-gray-200">
                            <strong>Requerimiento:</strong> {{ $solicitud->descripcion }}
                        </div>

                        <!-- Bitácora de Envíos en Cola (CP-17, SLA-05) -->
                        <div>
                            <h4 class="text-xs font-bold uppercase text-gray-600 mb-2">Bitácora de Despacho a Proveedores (SLA-05 / KPI-10)</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                @forelse($solicitud->envios as $envio)
                                    <div class="p-2 bg-white rounded border text-xs flex justify-between items-center">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $envio->proveedor->razon_social ?? 'Proveedor' }}</div>
                                            <div class="text-gray-400 text-[10px]">{{ $envio->proveedor->correo }} (Intentos: {{ $envio->intentos }})</div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold
                                            @if($envio->estado === 'enviado') bg-green-100 text-green-700
                                            @elseif($envio->estado === 'pendiente') bg-yellow-100 text-yellow-700
                                            @else bg-red-100 text-red-700 @endif
                                        ">
                                            {{ ucfirst($envio->estado) }}
                                        </span>
                                    </div>
                                @empty
                                    <div class="text-xs text-gray-400 italic">No se han registrado despachos para esta solicitud.</div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Propuestas Recibidas y Flujo de Adjudicación (KPI-08, CP-18) -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="text-xs font-bold uppercase text-gray-600">
                                    Propuestas Comerciales Recibidas (Total: {{ $solicitud->cotizaciones->count() }} de 2-3 exigidas)
                                </h4>
                                @if($solicitud->cotizaciones->count() < 2 && $solicitud->estado !== 'adjudicada')
                                    <span class="text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                        ⚠️ Cuota incompleta: Adjudicación requerirá excepción justificada (KPI-08).
                                    </span>
                                @endif
                            </div>

                            <div class="overflow-x-auto bg-white rounded border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead class="bg-gray-100 text-gray-700 uppercase">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Proveedor</th>
                                            <th class="px-3 py-2 text-left">Fecha Recepción</th>
                                            <th class="px-3 py-2 text-right">Monto Ofertado</th>
                                            <th class="px-3 py-2 text-center">Respaldo Adjunto</th>
                                            <th class="px-3 py-2 text-center">Estado Adjudicación</th>
                                            <th class="px-3 py-2 text-right">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @forelse($solicitud->cotizaciones as $cot)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 font-medium text-gray-900">{{ $cot->proveedor->razon_social }}</td>
                                                <td class="px-3 py-2 text-gray-600">{{ $cot->fecha_recepcion->format('d/m/Y') }}</td>
                                                <td class="px-3 py-2 text-right font-bold text-gray-900">${{ number_format($cot->monto_total, 0, ',', '.') }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    @if($cot->documentos->isNotEmpty())
                                                        <span class="text-blue-600">📎 {{ $cot->documentos->first()->nombre_original }}</span>
                                                    @else
                                                        <span class="text-gray-400">Sin archivo</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    @if($cot->adjudicacion)
                                                        <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded font-semibold">
                                                            Adjudicada {{ $cot->adjudicacion->excepcion_autorizada ? '(Por Excepción)' : '' }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">Pendiente</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    @if(! $cot->adjudicacion && $solicitud->estado !== 'adjudicada' && Auth::user()->rol === 'administrador')
                                                        <button
                                                            wire:click="abrirModalAdjudicar({{ $cot->id }})"
                                                            class="px-2.5 py-1 bg-red-700 hover:bg-red-800 text-white font-medium rounded text-[11px]"
                                                        >
                                                            Adjudicar
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-3 py-4 text-center text-gray-400">No se han registrado cotizaciones para esta solicitud.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-400 bg-gray-50 rounded-lg">
                        No hay solicitudes de cotización registradas.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Modal Nueva Solicitud -->
        @if($mostrarModalSolicitud)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-2">
                        <h3 class="text-lg font-bold text-gray-800">Nueva Solicitud de Cotización</h3>
                        <button wire:click="$set('mostrarModalSolicitud', false)" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    <form wire:submit.prevent="guardarSolicitud" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Carrera Solicitante *</label>
                            <select wire:model="carrera_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                @foreach(Carrera::where('activa', true)->get() as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                                @endforeach
                            </select>
                            @error('carrera_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Detalle y Requerimientos de Insumos *</label>
                            <textarea wire:model="descripcion" rows="3" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Detalle los insumos, cantidades requeridas y especificaciones técnicas..."></textarea>
                            @error('descripcion') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Proveedores a Notificar (Despacho en Cola)</label>
                            <div class="max-h-40 overflow-y-auto border border-gray-300 rounded-md p-2 space-y-1">
                                @foreach(Proveedor::where('activo', true)->orderBy('razon_social')->get() as $prov)
                                    <label class="flex items-center text-xs text-gray-800">
                                        <input type="checkbox" wire:model="proveedoresSeleccionados" value="{{ $prov->id }}" class="rounded border-gray-300 text-red-600 shadow-sm mr-2">
                                        <span>{{ $prov->razon_social }} ({{ $prov->correo }}) - <span class="text-gray-400">{{ $prov->tipo }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('proveedores') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end space-x-2 pt-4 border-t">
                            <button type="button" wire:click="$set('mostrarModalSolicitud', false)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white rounded-md text-sm font-semibold">Crear y Despachar</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Modal Cargar Cotización Recibida -->
        @if($mostrarModalCargarCotizacion)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-2">
                        <h3 class="text-lg font-bold text-gray-800">Registrar Propuesta de Cotización Recibida</h3>
                        <button wire:click="$set('mostrarModalCargarCotizacion', false)" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    <form wire:submit.prevent="guardarCotizacion" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Proveedor Oferente *</label>
                            <select wire:model="proveedorCotizacionId" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Seleccione un proveedor...</option>
                                @foreach(Proveedor::where('activo', true)->orderBy('razon_social')->get() as $p)
                                    <option value="{{ $p->id }}">{{ $p->razon_social }} (RUT: {{ $p->rut }})</option>
                                @endforeach
                            </select>
                            @error('proveedorCotizacionId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase">Monto Total Ofertado ($) *</label>
                                <input type="number" step="0.01" wire:model="montoCotizacion" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Ej. 250000" />
                                @error('montoCotizacion') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase">Fecha Recepción *</label>
                                <input type="date" wire:model="fechaRecepcion" class="w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Documento de Cotización Adjunto (PDF / Imagen)</label>
                            <input type="file" wire:model="archivoCotizacion" class="w-full border border-gray-300 rounded-md text-xs p-1" />
                            <p class="text-[10px] text-gray-400 mt-1">El archivo se conservará con cálculo de huella SHA-256 en la tabla de documentos.</p>
                        </div>

                        <div class="flex justify-end space-x-2 pt-4 border-t">
                            <button type="button" wire:click="$set('mostrarModalCargarCotizacion', false)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white rounded-md text-sm font-semibold">Guardar Cotización</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Modal Adjudicación (KPI-08, CP-18) -->
        @if($mostrarModalAdjudicar)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-2">
                        <h3 class="text-lg font-bold text-gray-800">Confirmar Adjudicación de Compra</h3>
                        <button wire:click="$set('mostrarModalAdjudicar', false)" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    @error('adjudicacion')
                        <div class="p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-xs rounded">
                            {{ $message }}
                        </div>
                    @enderror
                    @error('cotizacion')
                        <div class="p-3 bg-amber-50 border-l-4 border-amber-500 text-amber-800 text-xs rounded">
                            {{ $message }}
                        </div>
                    @enderror

                    <p class="text-sm text-gray-600">
                        La confirmación de la adjudicación valida la cuota mínima de 2 a 3 propuestas (KPI-08) y comprueba la disponibilidad presupuestaria de la carrera antes de comprometer fondos.
                    </p>

                    <div class="p-4 bg-gray-50 border rounded-lg space-y-3">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.live="excepcionAutorizada" class="rounded border-gray-300 text-red-600 shadow-sm mr-2">
                            <span class="text-sm font-semibold text-gray-800">Adjudicar por Excepción Autorizada (&lt; 2 cotizaciones)</span>
                        </label>

                        @if($excepcionAutorizada)
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase">Justificación Obligatoria de la Excepción (KPI-08) *</label>
                                <textarea wire:model="justificacionExcepcion" rows="3" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Especifique el motivo técnico, exclusividad de proveedor o urgencia que sustenta la excepción..."></textarea>
                                @error('justificacion') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" wire:click="$set('mostrarModalAdjudicar', false)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm">Cancelar</button>
                        <button type="button" wire:click="confirmarAdjudicacion(app(\App\Services\CotizacionService::class))" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white rounded-md text-sm font-semibold">
                            Confirmar Adjudicación
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
