<?php

use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Proveedor;
use App\Services\MantenimientoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    // Modal Programar
    public bool $mostrarModalProgramar = false;
    public ?int $equipo_id = null;
    public ?int $proveedor_id = null;
    public string $tipo = 'preventivo';
    public string $frecuencia = 'semestral';
    public string $fecha_programada = '';
    public string $observaciones = '';

    // Modal Ejecutar
    public bool $mostrarModalEjecutar = false;
    public ?int $mantenimientoId = null;
    public string $fecha_realizada = '';
    public string $observaciones_ejecucion = '';
    public $informe_tecnico = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Mantenimiento::class);
        $primerEquipo = Equipo::where('estado', '!=', 'dado_de_baja')->first();
        if ($primerEquipo) {
            $this->equipo_id = $primerEquipo->id;
        }
        $this->fecha_programada = now()->addMonths(6)->toDateString();
        $this->fecha_realizada = now()->toDateString();
    }

    public function mantenimientos()
    {
        return Mantenimiento::with(['equipo.ubicacion', 'proveedor', 'documentos'])
            ->latest('fecha_programada')
            ->get();
    }

    public function alertas(MantenimientoService $servicio)
    {
        return $servicio->evaluarAlertasMantenimiento();
    }

    public function equiposDisponibles()
    {
        return Equipo::where('estado', '!=', 'dado_de_baja')->orderBy('nombre')->get();
    }

    public function proveedores()
    {
        return Proveedor::where('activo', true)->orderBy('razon_social')->get();
    }

    public function abrirModalProgramar(): void
    {
        $this->resetErrorBag();
        $this->tipo = 'preventivo';
        $this->frecuencia = 'semestral';
        $this->fecha_programada = now()->addMonths(6)->toDateString();
        $this->observaciones = '';
        $this->mostrarModalProgramar = true;
    }

    public function guardarMantenimiento(MantenimientoService $servicio): void
    {
        Gate::authorize('create', Mantenimiento::class);

        $this->validate([
            'equipo_id' => 'required|exists:equipos,id',
            'tipo' => 'required|in:preventivo,correctivo',
            'fecha_programada' => 'required|date',
        ]);

        $equipo = Equipo::findOrFail($this->equipo_id);

        try {
            $servicio->programarMantenimiento(
                $equipo,
                $this->tipo,
                $this->tipo === 'preventivo' ? $this->frecuencia : 'no_aplica',
                $this->fecha_programada,
                $this->proveedor_id ? (int) $this->proveedor_id : null,
                $this->observaciones
            );

            $this->mostrarModalProgramar = false;
            session()->flash('mensaje', 'Mantenimiento programado con éxito.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }
        }
    }

    public function abrirModalEjecutar(int $id): void
    {
        $this->resetErrorBag();
        $this->mantenimientoId = $id;
        $this->fecha_realizada = now()->toDateString();
        $this->observaciones_ejecucion = '';
        $this->informe_tecnico = null;
        $this->mostrarModalEjecutar = true;
    }

    public function registrarEjecucion(MantenimientoService $servicio): void
    {
        $this->validate([
            'fecha_realizada' => 'required|date',
            'informe_tecnico' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'informe_tecnico.mimes' => 'El informe técnico debe estar en formato PDF, JPG o PNG.',
            'informe_tecnico.max' => 'El archivo no debe exceder los 10 MB.',
        ]);

        $mant = Mantenimiento::findOrFail($this->mantenimientoId);
        Gate::authorize('update', $mant);
        $servicio->registrarEjecucion(
            $mant,
            $this->fecha_realizada,
            $this->observaciones_ejecucion,
            $this->informe_tecnico,
            Auth::user()
        );

        $this->mostrarModalEjecutar = false;
        session()->flash('mensaje', 'Mantenimiento registrado como realizado y activo reintegrado a servicio.');
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Mantenimiento Preventivo y Alertas Técnicas</h1>
                <p class="text-sm text-gray-500 mt-1">Calendarización semestral/anual, alertas por criticidad del activo (CP-05) y bitácora técnica de intervenciones.</p>
            </div>
            @if(Auth::user()->rol !== 'docente')
                <button wire:click="abrirModalProgramar" class="inline-flex items-center px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-md shadow-sm transition">
                    <svg class="w-5 h-5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Programar Mantenimiento
                </button>
            @endif
        </div>

        @if(session()->has('mensaje'))
            <div class="p-4 bg-green-50 border-l-4 border-green-600 text-green-800 text-sm rounded-r-md">
                {{ session('mensaje') }}
            </div>
        @endif

        <!-- Panel de Alertas Preventivas y Vencidas (CP-05) -->
        @php $alertasActivas = $this->alertas(app(\App\Services\MantenimientoService::class)); @endphp
        @if(!empty($alertasActivas))
            <div class="bg-white rounded-lg shadow-sm border border-orange-200 p-5 space-y-3">
                <div class="flex items-center gap-2 text-orange-900 font-bold text-base">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Alertas del Planificador de Tareas (Scheduler de Laravel)
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($alertasActivas as $alerta)
                        <div class="p-3 rounded-md border text-xs {{ $alerta['tipo_alerta'] === 'vencida' ? 'bg-red-50 border-red-200 text-red-900' : 'bg-yellow-50 border-yellow-200 text-yellow-900' }}">
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-bold uppercase tracking-wider text-[10px] px-1.5 py-0.5 rounded {{ $alerta['tipo_alerta'] === 'vencida' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800' }}">
                                    {{ $alerta['tipo_alerta'] === 'vencida' ? 'Vencido' : 'Alerta Preventiva' }}
                                </span>
                                <span class="font-semibold text-gray-500">Criticidad {{ ucfirst($alerta['criticidad']) }}</span>
                            </div>
                            <div class="font-semibold text-sm line-clamp-1">{{ $alerta['equipo_nombre'] }}</div>
                            <div class="mt-1 text-gray-600">Fecha objetivo: <span class="font-mono">{{ $alerta['fecha_programada'] }}</span></div>
                            <div class="font-medium mt-0.5 {{ $alerta['dias_restantes'] < 0 ? 'text-red-700 font-bold' : 'text-gray-700' }}">
                                {{ $alerta['dias_restantes'] < 0 ? 'Atrasado por ' . abs($alerta['dias_restantes']) . ' día(s)' : 'Vence en ' . $alerta['dias_restantes'] . ' día(s)' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Tabla de Mantenimientos -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                        <tr>
                            <th class="px-6 py-3 text-left">Equipo</th>
                            <th class="px-6 py-3 text-left">Tipo / Frecuencia</th>
                            <th class="px-6 py-3 text-left">Criticidad</th>
                            <th class="px-6 py-3 text-left">Fecha Programada</th>
                            <th class="px-6 py-3 text-left">Fecha Realizada</th>
                            <th class="px-6 py-3 text-left">Estado</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($this->mantenimientos() as $m)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $m->equipo?->nombre }}
                                    <div class="text-xs text-gray-500 font-mono">{{ $m->equipo?->codigo_barras }} · {{ $m->equipo?->ubicacion?->nombre }}</div>
                                </td>
                                <td class="px-6 py-4 capitalize text-gray-700">
                                    {{ $m->tipo }} ({{ $m->frecuencia ?? 'N/A' }})
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php $c = $m->equipo?->criticidad; @endphp
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $c === 'alta' ? 'bg-red-100 text-red-800' : ($c === 'media' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800') }}">
                                        {{ ucfirst($c ?? 'media') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-800">
                                    {{ $m->fecha_programada->toDateString() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-600">
                                    {{ $m->fecha_realizada ? $m->fecha_realizada->toDateString() : 'Pendiente' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($m->estado === 'realizado')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">Realizado</span>
                                    @elseif($m->estado === 'vencido')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">Vencido</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">Programado</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    @if($m->estado !== 'realizado' && Auth::user()->rol !== 'docente')
                                        <button wire:click="abrirModalEjecutar({{ $m->id }})" class="text-xs font-medium text-green-700 hover:text-green-900 bg-green-50 px-2.5 py-1 rounded border border-green-200">
                                            Registrar Ejecución
                                        </button>
                                    @elseif($m->documentos->isNotEmpty())
                                        <span class="text-xs text-blue-600">Informe adjunto</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">No hay planes de mantenimiento registrados actualmente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Programar Mantenimiento -->
    @if($mostrarModalProgramar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Programar Mantenimiento Preventivo / Correctivo</h3>
                    <button wire:click="$set('mostrarModalProgramar', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Equipo a Intervenir</label>
                        <select wire:model="equipo_id" class="w-full text-sm rounded-md border-gray-300">
                            @foreach($this->equiposDisponibles() as $e)
                                <option value="{{ $e->id }}">{{ $e->nombre }} (Criticidad: {{ strtoupper($e->criticidad) }})</option>
                            @endforeach
                        </select>
                        @error('equipo_id') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Tipo</label>
                            <select wire:model.live="tipo" class="w-full text-sm rounded-md border-gray-300">
                                <option value="preventivo">Preventivo</option>
                                <option value="correctivo">Correctivo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Periodicidad (CP-05)</label>
                            <select wire:model="frecuencia" class="w-full text-sm rounded-md border-gray-300" {{ $tipo === 'correctivo' ? 'disabled' : '' }}>
                                <option value="semestral">Semestral (6 meses)</option>
                                <option value="anual">Anual (12 meses)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Fecha Programada</label>
                            <input wire:model="fecha_programada" type="date" class="w-full text-sm rounded-md border-gray-300">
                            @error('fecha_programada') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Proveedor Externo (Opcional)</label>
                            <select wire:model="proveedor_id" class="w-full text-sm rounded-md border-gray-300">
                                <option value="">Mantenimiento Interno</option>
                                @foreach($this->proveedores() as $prov)
                                    <option value="{{ $prov->id }}">{{ $prov->razon_social }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Observaciones / Plan de Trabajo</label>
                        <textarea wire:model="observaciones" rows="2" class="w-full text-sm rounded-md border-gray-300" placeholder="Puntos a revisar o calibrar..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalProgramar', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="guardarMantenimiento" type="button" class="px-4 py-2 text-sm bg-red-700 hover:bg-red-800 text-white font-medium rounded-md">Guardar Programación</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Registrar Ejecución -->
    @if($mostrarModalEjecutar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Registrar Mantenimiento Realizado</h3>
                    <button wire:click="$set('mostrarModalEjecutar', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Fecha de Ejecución</label>
                        <input wire:model="fecha_realizada" type="date" class="w-full text-sm rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Detalle de Trabajos Realizados</label>
                        <textarea wire:model="observaciones_ejecucion" rows="3" class="w-full text-sm rounded-md border-gray-300" placeholder="Limpieza, cambio de pasta térmica, firmware..."></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Adjuntar Informe Técnico (PDF o Imagen)</label>
                        <input wire:model="informe_tecnico" type="file" class="w-full text-sm text-gray-500">
                        @error('informe_tecnico') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalEjecutar', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="registrarEjecucion" type="button" class="px-4 py-2 text-sm bg-green-700 hover:bg-green-800 text-white font-medium rounded-md">Confirmar y Reintegrar Equipo</button>
                </div>
            </div>
        </div>
    @endif
</div>
