<?php

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Ubicacion;
use App\Services\BarcodeService;
use App\Services\EquipoService;
use App\Services\FallaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $buscar = '';
    public string $filtroCriticidad = '';
    public string $filtroEstado = '';
    public ?int $filtroUbicacionId = null;

    // Formulario de registro de equipo
    public bool $mostrarModalCrear = false;
    public string $codigo_barras = '';
    public string $nombre = '';
    public string $marca = '';
    public string $modelo = '';
    public string $numero_serie = '';
    public string $criticidad = 'media';
    public ?int $ubicacion_id = null;
    public $fotografia = null;

    // Reporte de fallas
    public bool $mostrarModalFalla = false;
    public ?int $equipoFallaId = null;
    public string $descripcionFalla = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Equipo::class);
        $primeraUbicacion = Ubicacion::first();
        if ($primeraUbicacion) {
            $this->ubicacion_id = $primeraUbicacion->id;
        }
    }

    public function equipos()
    {
        $query = Equipo::with(['ubicacion.carrera', 'documentos']);

        if (trim($this->buscar) !== '') {
            $t = '%' . trim($this->buscar) . '%';
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'LIKE', $t)
                  ->orWhere('codigo_barras', 'LIKE', $t)
                  ->orWhere('marca', 'LIKE', $t)
                  ->orWhere('modelo', 'LIKE', $t)
                  ->orWhere('numero_serie', 'LIKE', $t);
            });
        }

        if ($this->filtroCriticidad !== '') {
            $query->where('criticidad', $this->filtroCriticidad);
        }

        if ($this->filtroEstado !== '') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroUbicacionId) {
            $query->where('ubicacion_id', $this->filtroUbicacionId);
        }

        return $query->latest()->get();
    }

    public function ubicaciones()
    {
        return Ubicacion::with('carrera')->orderBy('nombre')->get();
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('create', Equipo::class);
        $this->resetErrorBag();
        $this->codigo_barras = 'EQ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $this->nombre = '';
        $this->marca = '';
        $this->modelo = '';
        $this->numero_serie = '';
        $this->criticidad = 'media';
        $this->fotografia = null;
        $this->mostrarModalCrear = true;
    }

    public function guardarEquipo(EquipoService $equipoService): void
    {
        Gate::authorize('create', Equipo::class);

        $this->validate([
            'fotografia' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'fotografia.mimes' => 'La fotografía debe tener formato JPG, PNG o WEBP.',
            'fotografia.max' => 'La fotografía no debe superar los 5 MB.',
        ]);

        try {
            $equipoService->registrarEquipo([
                'codigo_barras' => $this->codigo_barras,
                'nombre' => $this->nombre,
                'marca' => $this->marca,
                'modelo' => $this->modelo,
                'numero_serie' => $this->numero_serie,
                'criticidad' => $this->criticidad,
                'ubicacion_id' => (int) $this->ubicacion_id,
                'fotografia' => $this->fotografia,
            ], Auth::user());

            $this->mostrarModalCrear = false;
            session()->flash('mensaje', 'Equipo registrado exitosamente con ficha técnica completa.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }
        }
    }

    public function abrirModalFalla(int $equipoId): void
    {
        $this->resetErrorBag();
        $this->equipoFallaId = $equipoId;
        $this->descripcionFalla = '';
        $this->mostrarModalFalla = true;
    }

    public function registrarFalla(FallaService $fallaService): void
    {
        $this->validate([
            'descripcionFalla' => 'required|string|min:10',
        ], [
            'descripcionFalla.required' => 'Debe ingresar el detalle técnico del desperfecto.',
            'descripcionFalla.min' => 'La descripción debe tener al menos 10 caracteres.',
        ]);

        $equipo = Equipo::findOrFail($this->equipoFallaId);
        Gate::authorize('reportarFalla', $equipo);
        $fallaService->reportarFalla($equipo, Auth::user(), $this->descripcionFalla);

        $this->mostrarModalFalla = false;
        session()->flash('mensaje', 'Falla reportada con éxito. El equipo ha cambiado preventivamente a estado En Mantenimiento.');
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Catálogo de Equipos y Fichas Técnicas</h1>
                <p class="text-sm text-gray-500 mt-1">Gestión de activos, nombres estandarizados, criticidad y ciclo de vida institucional.</p>
            </div>
            @if(Auth::user()->rol !== 'docente')
                <button wire:click="abrirModalCrear" class="inline-flex items-center px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-md shadow-sm transition">
                    <svg class="w-5 h-5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Equipo
                </button>
            @endif
        </div>

        @if(session()->has('mensaje'))
            <div class="p-4 bg-green-50 border-l-4 border-green-600 text-green-800 text-sm rounded-r-md">
                {{ session('mensaje') }}
            </div>
        @endif

        <!-- Filtros y Búsqueda -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Buscar</label>
                <input wire:model.live.debounce.300ms="buscar" type="text" placeholder="Nombre estandarizado, serie..." class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Criticidad</label>
                <select wire:model.live="filtroCriticidad" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                    <option value="">Todas</option>
                    <option value="alta">Alta</option>
                    <option value="media">Media</option>
                    <option value="baja">Baja</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Estado</label>
                <select wire:model.live="filtroEstado" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                    <option value="">Todos</option>
                    <option value="disponible">Disponible</option>
                    <option value="en_mantenimiento">En Mantenimiento</option>
                    <option value="prestado">Prestado</option>
                    <option value="no_disponible">No Disponible</option>
                    <option value="pendiente_resolucion">Pendiente Resolución</option>
                    <option value="dado_de_baja">Dado de Baja</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Laboratorio / Pañol</label>
                <select wire:model.live="filtroUbicacionId" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                    <option value="">Todas las ubicaciones</option>
                    @foreach($this->ubicaciones() as $ub)
                        <option value="{{ $ub->id }}">{{ $ub->nombre }} ({{ $ub->carrera?->codigo }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Tabla de Equipos -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                        <tr>
                            <th class="px-6 py-3 text-left">Código / Barras</th>
                            <th class="px-6 py-3 text-left">Nombre Estandarizado</th>
                            <th class="px-6 py-3 text-left">Marca / Modelo</th>
                            <th class="px-6 py-3 text-left">Criticidad</th>
                            <th class="px-6 py-3 text-left">Estado</th>
                            <th class="px-6 py-3 text-left">Ubicación</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($this->equipos() as $eq)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-medium text-gray-900">
                                    {{ $eq->codigo_barras }}
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $eq->nombre }}
                                    <div class="text-xs text-gray-500">S/N: {{ $eq->numero_serie }}</div>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    {{ $eq->marca }} {{ $eq->modelo }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($eq->criticidad === 'alta')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Alta</span>
                                    @elseif($eq->criticidad === 'media')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Media</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Baja</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-status-badge :estado="$eq->estado" />
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    {{ $eq->ubicacion?->nombre }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                    @if($eq->estado !== 'dado_de_baja')
                                        <button wire:click="abrirModalFalla({{ $eq->id }})" class="text-xs text-orange-600 hover:text-orange-900 font-medium">Reportar Falla</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">No se encontraron equipos registrados que coincidan con la búsqueda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Registro de Ficha Técnica -->
    @if($mostrarModalCrear)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Registrar Ficha Técnica de Equipo</h3>
                    <button wire:click="$set('mostrarModalCrear', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Nombre Estandarizado (CP-04)</label>
                        <input wire:model="nombre" type="text" placeholder="Ej: Computador de Escritorio All-in-One Intel Core i7 - Lab Redes" class="w-full text-sm rounded-md border-gray-300">
                        <p class="text-xs text-gray-500 mt-1">Debe ser descriptivo. Prohibidas siglas como PC-01, SW-L2.</p>
                        @error('nombre') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Código de Barras</label>
                            <input wire:model="codigo_barras" type="text" class="w-full text-sm rounded-md border-gray-300 font-mono">
                            @error('codigo_barras') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Número de Serie</label>
                            <input wire:model="numero_serie" type="text" placeholder="SN-12345678" class="w-full text-sm rounded-md border-gray-300">
                            @error('numero_serie') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Marca</label>
                            <input wire:model="marca" type="text" placeholder="Ej: Cisco, HP, Dell" class="w-full text-sm rounded-md border-gray-300">
                            @error('marca') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Modelo</label>
                            <input wire:model="modelo" type="text" placeholder="Ej: Catalyst 2960-X" class="w-full text-sm rounded-md border-gray-300">
                            @error('modelo') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Criticidad</label>
                            <select wire:model="criticidad" class="w-full text-sm rounded-md border-gray-300">
                                <option value="alta">Alta (Mantenimiento urgente / 30 días alerta)</option>
                                <option value="media">Media (15 días alerta)</option>
                                <option value="baja">Baja (7 días alerta)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Ubicación / Laboratorio</label>
                            <select wire:model="ubicacion_id" class="w-full text-sm rounded-md border-gray-300">
                                @foreach($this->ubicaciones() as $ub)
                                    <option value="{{ $ub->id }}">{{ $ub->nombre }} ({{ $ub->carrera?->codigo }})</option>
                                @endforeach
                            </select>
                            @error('ubicacion_id') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Fotografía del Equipo</label>
                        <input wire:model="fotografia" type="file" accept="image/*" class="w-full text-sm text-gray-500">
                        @error('fotografia') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalCrear', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="guardarEquipo" type="button" class="px-4 py-2 text-sm bg-red-700 hover:bg-red-800 text-white font-medium rounded-md">Guardar Equipo</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Reporte de Falla -->
    @if($mostrarModalFalla)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Reportar Falla o Anomalía Técnica</h3>
                    <button wire:click="$set('mostrarModalFalla', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Descripción del Desperfecto</label>
                    <textarea wire:model="descripcionFalla" rows="4" placeholder="Describa con precisión los síntomas, errores o daños observados..." class="w-full text-sm rounded-md border-gray-300"></textarea>
                    @error('descripcionFalla') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                </div>

                <div class="p-3 bg-yellow-50 rounded text-xs text-yellow-800">
                    Al confirmar, el activo pasará automáticamente a estado <strong>En Mantenimiento</strong> y no podrá ser despachado a nuevos préstamos hasta su revisión técnica.
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalFalla', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="registrarFalla" type="button" class="px-4 py-2 text-sm bg-orange-600 hover:bg-orange-700 text-white font-medium rounded-md">Confirmar Reporte</button>
                </div>
            </div>
        </div>
    @endif
</div>
