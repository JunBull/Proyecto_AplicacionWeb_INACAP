<?php

use App\Models\BajaReemplazo;
use App\Models\Equipo;
use App\Services\BajaReemplazoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    // Modal Propuesta
    public bool $mostrarModalProponer = false;
    public string $codigoBarras = '';
    public ?int $equipo_id = null;
    public ?Equipo $equipoSeleccionado = null;
    public ?string $errorEquipo = null;
    public string $tipo = 'baja';
    public string $motivo = '';
    public $adjuntos = [];

    // Modal Resolución
    public bool $mostrarModalResolver = false;
    public ?int $propuestaId = null;
    public string $observacionesResolucion = '';
    public bool $aprobar = true;

    public function mount(): void
    {
        Gate::authorize('viewAny', BajaReemplazo::class);
    }

    public function propuestas()
    {
        $query = BajaReemplazo::with(['equipo.ubicacion', 'proponente', 'aprobador', 'documentos'])
            ->latest();

        if (! Auth::user()->isAdmin()) {
            $query->where('propuesta_por', Auth::id());
        }

        return $query->get();
    }

    public function updatedCodigoBarras(): void
    {
        $this->buscarEquipo();
    }

    public function buscarEquipo(): void
    {
        $this->errorEquipo = null;
        $this->equipoSeleccionado = null;
        $this->equipo_id = null;

        $codigo = trim($this->codigoBarras);
        if ($codigo === '') {
            return;
        }

        $equipo = Equipo::with('ubicacion')->where('codigo_barras', $codigo)->first();

        if (! $equipo) {
            $this->errorEquipo = "El código '{$codigo}' no está registrado en el inventario institucional.";
            return;
        }

        if ($equipo->estado === 'dado_de_baja') {
            $this->errorEquipo = "El equipo '{$equipo->nombre}' ({$codigo}) ya fue dado de baja formalmente.";
            return;
        }

        if ($equipo->estado === 'pendiente_resolucion') {
            $this->errorEquipo = "El equipo '{$equipo->nombre}' ({$codigo}) ya tiene una propuesta de baja pendiente de resolución.";
            return;
        }

        if ($equipo->estado === 'prestado') {
            $this->errorEquipo = "El equipo '{$equipo->nombre}' ({$codigo}) está actualmente prestado; debe registrarse su devolución antes de tramitar la baja.";
            return;
        }

        $this->equipoSeleccionado = $equipo;
        $this->equipo_id = $equipo->id;
    }

    public function abrirModalProponer(): void
    {
        $this->resetErrorBag();
        $this->codigoBarras = '';
        $this->equipo_id = null;
        $this->equipoSeleccionado = null;
        $this->errorEquipo = null;
        $this->tipo = 'baja';
        $this->motivo = '';
        $this->adjuntos = [];
        $this->mostrarModalProponer = true;
    }

    public function guardarPropuesta(BajaReemplazoService $servicio): void
    {
        Gate::authorize('create', BajaReemplazo::class);

        $this->buscarEquipo();

        if ($this->errorEquipo || ! $this->equipo_id) {
            $this->addError('codigoBarras', $this->errorEquipo ?? 'Debe escanear o ingresar un código de equipo válido.');
            return;
        }

        $this->validate([
            'equipo_id' => 'required|exists:equipos,id',
            'tipo' => 'required|in:baja,reemplazo',
            'motivo' => 'required|string|min:15',
            'adjuntos.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'equipo_id.required' => 'Debe seleccionar o escanear un equipo válido.',
            'motivo.required' => 'Debe ingresar el sustento técnico y económico que justifique reparación vs reposición.',
            'motivo.min' => 'El sustento debe tener al menos 15 caracteres explicativos.',
            'adjuntos.*.mimes' => 'Los documentos de respaldo deben estar en formato PDF, JPG o PNG.',
            'adjuntos.*.max' => 'Los archivos no deben superar 10 MB.',
        ]);

        $equipo = Equipo::findOrFail($this->equipo_id);

        try {
            $servicio->proponerBajaOReemplazo(
                $equipo,
                Auth::user(),
                $this->tipo,
                $this->motivo,
                null,
                is_array($this->adjuntos) ? $this->adjuntos : [$this->adjuntos]
            );

            $this->mostrarModalProponer = false;
            session()->flash('mensaje', 'Propuesta de ' . $this->tipo . ' formalizada con respaldos adjuntos. Equipo puesto en revisión.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }
        }
    }

    public function abrirModalResolver(int $id, bool $aprobar): void
    {
        $this->resetErrorBag();
        $this->propuestaId = $id;
        $this->aprobar = $aprobar;
        $this->observacionesResolucion = '';
        $this->mostrarModalResolver = true;
    }

    public function resolverPropuesta(BajaReemplazoService $servicio): void
    {
        $propuesta = BajaReemplazo::findOrFail($this->propuestaId);
        Gate::authorize('aprobar', $propuesta);

        try {
            $servicio->resolverPropuesta(
                $propuesta,
                Auth::user(),
                $this->aprobar,
                $this->observacionesResolucion
            );

            $this->mostrarModalResolver = false;
            $mensaje = $this->aprobar
                ? 'Propuesta aprobada. El equipo ha sido dado de baja formalmente y queda inhabilitado para préstamos.'
                : 'Propuesta rechazada por el Administrador. El equipo se mantiene no disponible pendiente de nueva resolución.';

            session()->flash('mensaje', $mensaje);
        } catch (\Exception $e) {
            $this->addError('resolucion', $e->getMessage());
        }
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Bajas Técnicas y Propuestas de Reemplazo</h1>
                <p class="text-sm text-gray-500 mt-1">Evaluación de costos de reparación vs. reposición y autorización de la Dirección.</p>
            </div>
            @if(Auth::user()->rol !== 'docente')
                <button wire:click="abrirModalProponer" class="inline-flex items-center px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-md shadow-sm transition">
                    <svg class="w-5 h-5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nueva Propuesta
                </button>
            @endif
        </div>

        @if(session()->has('mensaje'))
            <div class="p-4 bg-green-50 border-l-4 border-green-600 text-green-800 text-sm rounded-r-md">
                {{ session('mensaje') }}
            </div>
        @endif

        <!-- Tabla de Propuestas -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                        <tr>
                            <th class="px-6 py-3 text-left">Equipo</th>
                            <th class="px-6 py-3 text-left">Tipo</th>
                            <th class="px-6 py-3 text-left">Sustento Reparación vs Reposición</th>
                            <th class="px-6 py-3 text-left">Propuesto Por</th>
                            <th class="px-6 py-3 text-left">Adjuntos</th>
                            <th class="px-6 py-3 text-left">Estado</th>
                            <th class="px-6 py-3 text-right">Resolución</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($this->propuestas() as $prop)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $prop->equipo?->nombre }}
                                    <div class="text-xs text-gray-500 font-mono">{{ $prop->equipo?->codigo_barras }} · Estado: {{ $prop->equipo?->estado }}</div>
                                </td>
                                <td class="px-6 py-4 uppercase font-semibold text-xs whitespace-nowrap {{ $prop->tipo === 'reemplazo' ? 'text-blue-700' : 'text-red-700' }}">
                                    {{ $prop->tipo }}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-700 max-w-xs">
                                    {{ $prop->motivo }}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-600 whitespace-nowrap">
                                    {{ $prop->proponente?->nombre }}
                                    <div class="text-[11px] text-gray-400 font-mono">{{ $prop->fecha ? $prop->fecha->toDateString() : '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    @if($prop->documentos->isNotEmpty())
                                        <span class="inline-flex items-center text-blue-600 font-medium">
                                            📄 {{ $prop->documentos->count() }} documento(s)
                                        </span>
                                    @else
                                        <span class="text-gray-400">Sin adjuntos</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($prop->estado === 'aprobada')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">Aprobada</span>
                                    @elseif($prop->estado === 'rechazada')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">Rechazada</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">Pendiente Aprobación</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap space-x-2">
                                    @if($prop->estado === 'propuesta' && Auth::user()->rol === 'administrador')
                                        <button wire:click="abrirModalResolver({{ $prop->id }}, true)" class="text-xs bg-green-600 hover:bg-green-700 text-white font-medium px-2.5 py-1 rounded">Aprobar</button>
                                        <button wire:click="abrirModalResolver({{ $prop->id }}, false)" class="text-xs bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1 rounded">Rechazar</button>
                                    @elseif($prop->aprobador)
                                        <div class="text-xs text-gray-500">Resuelto por {{ $prop->aprobador?->nombre }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">No existen solicitudes de baja o reemplazo registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Proponer Baja / Reemplazo -->
    @if($mostrarModalProponer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Proponer Baja o Reemplazo de Activo</h3>
                    <button wire:click="$set('mostrarModalProponer', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Código del Equipo (Escanear o Escribir)</label>
                        <div class="relative">
                            <input
                                wire:model.live.debounce.300ms="codigoBarras"
                                wire:keydown.enter.prevent="buscarEquipo"
                                type="text"
                                placeholder="Escanee con lector o escriba el código (ej. EQ-SW-CSC-2960-01)..."
                                autofocus
                                class="w-full text-sm rounded-md font-mono border-gray-300 focus:border-red-600 focus:ring-red-600 uppercase pr-10"
                            >
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h-1M5 12H4m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>

                        @if($errorEquipo)
                            <div class="mt-2 p-2.5 bg-red-50 border border-red-200 rounded-md flex items-start gap-2 text-xs text-red-700">
                                <svg class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>{{ $errorEquipo }}</span>
                            </div>
                        @elseif($equipoSeleccionado)
                            <div class="mt-2 p-3 bg-green-50 border border-green-200 rounded-md">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-green-900">{{ $equipoSeleccionado->nombre }}</span>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded uppercase bg-green-200 text-green-800">
                                        {{ $equipoSeleccionado->estado }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-green-800 space-y-0.5">
                                    <div><span class="font-semibold">Código:</span> <span class="font-mono">{{ $equipoSeleccionado->codigo_barras }}</span> &bull; <span class="font-semibold">Marca/Modelo:</span> {{ $equipoSeleccionado->marca }} {{ $equipoSeleccionado->modelo }}</div>
                                    @if($equipoSeleccionado->numero_serie)
                                        <div><span class="font-semibold">N° Serie:</span> {{ $equipoSeleccionado->numero_serie }}</div>
                                    @endif
                                    @if($equipoSeleccionado->ubicacion)
                                        <div><span class="font-semibold">Ubicación:</span> {{ $equipoSeleccionado->ubicacion->nombre }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @error('codigoBarras') <span class="text-xs text-red-600 font-medium block mt-1">{{ $message }}</span> @enderror
                        @error('equipo_id') <span class="text-xs text-red-600 font-medium block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Tipo de Propuesta</label>
                        <select wire:model="tipo" class="w-full text-sm rounded-md border-gray-300">
                            <option value="baja">Baja Definitiva (Sin reposición)</option>
                            <option value="reemplazo">Baja con Solicitud de Reemplazo (Adquisición)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Sustento Técnico y Económico (Reparación vs Reposición)</label>
                        <textarea wire:model="motivo" rows="4" placeholder="Ej: Cotización de reparación supera el 75% del costo de un equipo nuevo equivalente con garantía vigente..." class="w-full text-sm rounded-md border-gray-300"></textarea>
                        @error('motivo') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Adjuntar Cotizaciones / Informes Técnicos</label>
                        <input wire:model="adjuntos" type="file" multiple class="w-full text-sm text-gray-500">
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalProponer', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button
                        wire:click="guardarPropuesta"
                        type="button"
                        @disabled(! $equipoSeleccionado || $errorEquipo)
                        class="px-4 py-2 text-sm bg-red-700 hover:bg-red-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium rounded-md shadow-sm transition"
                    >
                        Enviar Propuesta a Dirección
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Resolver Propuesta -->
    @if($mostrarModalResolver)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $aprobar ? 'Aprobar Solicitud de Baja' : 'Rechazar Solicitud de Baja' }}
                    </h3>
                    <button wire:click="$set('mostrarModalResolver', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                @error('resolucion')
                    <div class="p-3 bg-red-50 text-red-800 text-xs rounded border border-red-200">
                        {{ $message }}
                    </div>
                @enderror

                @if($aprobar)
                    <div class="p-3 bg-red-50 rounded text-xs text-red-800 font-medium">
                        ATENCIÓN: Al aprobar la baja, el equipo cambiará a estado <strong>Dado de Baja</strong> y quedará inhabilitado de forma definitiva para préstamos institucionales.
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Observaciones de la Resolución</label>
                    <textarea wire:model="observacionesResolucion" rows="3" placeholder="Comentarios u orden de compra asociada..." class="w-full text-sm rounded-md border-gray-300"></textarea>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalResolver', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="resolverPropuesta" type="button" class="px-4 py-2 text-sm font-medium text-white rounded-md {{ $aprobar ? 'bg-green-700 hover:bg-green-800' : 'bg-red-700 hover:bg-red-800' }}">
                        {{ $aprobar ? 'Confirmar Aprobación' : 'Confirmar Rechazo' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
