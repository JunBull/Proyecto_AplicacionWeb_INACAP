<?php

use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Mantenimiento;
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
    public string $filtroCategoriaId = '';
    public string $filtroEstado = '';
    public ?int $filtroUbicacionId = null;

    // Control de categorías expandidas en acordeón
    public array $categoriasAbiertas = [];

    // Formulario de registro de equipo
    public bool $mostrarModalCrear = false;
    public string $codigo_barras = '';
    public string $nombre = '';
    public string $marca = '';
    public string $modelo = '';
    public string $numero_serie = '';
    public ?int $categoria_id = null;
    public ?int $ubicacion_id = null;
    public $fotografia = null;

    // Reporte de fallas
    public bool $mostrarModalFalla = false;
    public ?int $equipoFallaId = null;
    public string $descripcionFalla = '';

    // Hoja de Vida de Mantenimiento
    public bool $mostrarModalHojaVida = false;
    public ?int $equipoHojaVidaId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Equipo::class);

        if (Categoria::count() === 0) {
            (new \Database\Seeders\CategoriasEquiposSeeder())->run();
        }

        $primeraUbicacion = Ubicacion::first();
        if ($primeraUbicacion) {
            $this->ubicacion_id = $primeraUbicacion->id;
        }

        $primeraCategoria = Categoria::orderBy('orden')->first();
        if ($primeraCategoria) {
            $this->categoria_id = $primeraCategoria->id;
        }

        // Por defecto abrimos las categorías para explorar el catálogo
        $this->categoriasAbiertas = Categoria::pluck('id')->toArray();

        $catOsc = Categoria::where('slug', 'instrumentacion-electronica')->value('id');
        $catDef = Categoria::where('slug', 'computo-movil')->value('id') ?? Categoria::first()?->id;
        Equipo::whereNull('categoria_id')->each(function (Equipo $eq) use ($catOsc, $catDef) {
            if (stripos($eq->nombre, 'osciloscopio') !== false || stripos($eq->nombre, 'multímetro') !== false || stripos($eq->nombre, 'tektronix') !== false) {
                $eq->update(['categoria_id' => $catOsc ?? $catDef]);
            } else {
                $eq->update(['categoria_id' => $catDef]);
            }
        });
    }

    public function toggleCategoria(int $categoriaId): void
    {
        if (in_array($categoriaId, $this->categoriasAbiertas, true)) {
            $this->categoriasAbiertas = array_values(array_diff($this->categoriasAbiertas, [$categoriaId]));
        } else {
            $this->categoriasAbiertas[] = $categoriaId;
        }
    }

    public function expandirTodas(): void
    {
        $this->categoriasAbiertas = Categoria::pluck('id')->toArray();
    }

    public function colapsarTodas(): void
    {
        $this->categoriasAbiertas = [];
    }

    public function categorias()
    {
        return Categoria::where('activo', true)->orderBy('orden')->get();
    }

    public function ubicaciones()
    {
        return Ubicacion::with('carrera')->orderBy('nombre')->get();
    }

    /**
     * Obtiene las categorías agrupadas con sus respectivos equipos aplicando filtros globales.
     */
    public function gruposCategorias()
    {
        $termino = trim($this->buscar);
        $todasCategorias = Categoria::where('activo', true)->orderBy('orden')->get();

        if ($todasCategorias->isEmpty()) {
            (new \Database\Seeders\CategoriasEquiposSeeder())->run();
            $todasCategorias = Categoria::where('activo', true)->orderBy('orden')->get();
            $this->categoriasAbiertas = $todasCategorias->pluck('id')->toArray();
        }

        $catOsc = Categoria::where('slug', 'instrumentacion-electronica')->value('id');
        $catDef = Categoria::where('slug', 'computo-movil')->value('id') ?? Categoria::first()?->id;
        Equipo::whereNull('categoria_id')->each(function (Equipo $eq) use ($catOsc, $catDef) {
            if (stripos($eq->nombre, 'osciloscopio') !== false || stripos($eq->nombre, 'multímetro') !== false || stripos($eq->nombre, 'tektronix') !== false) {
                $eq->update(['categoria_id' => $catOsc ?? $catDef]);
            } else {
                $eq->update(['categoria_id' => $catDef]);
            }
        });

        if (empty($this->categoriasAbiertas) && $termino === '') {
            $this->categoriasAbiertas = $todasCategorias->pluck('id')->toArray();
        }

        // Si se especificó un filtro directo de categoría, filtramos la lista
        if ($this->filtroCategoriaId !== '') {
            $todasCategorias = $todasCategorias->where('id', (int) $this->filtroCategoriaId);
        }

        $resultado = [];

        foreach ($todasCategorias as $cat) {
            $query = Equipo::with(['ubicacion.carrera', 'documentos', 'categoria'])
                ->where('categoria_id', $cat->id);

            if ($termino !== '') {
                $t = '%' . $termino . '%';
                $query->where(function ($q) use ($t) {
                    $q->where('nombre', 'LIKE', $t)
                      ->orWhere('codigo_barras', 'LIKE', $t)
                      ->orWhere('marca', 'LIKE', $t)
                      ->orWhere('modelo', 'LIKE', $t)
                      ->orWhere('numero_serie', 'LIKE', $t);
                });
            }

            if ($this->filtroEstado !== '') {
                $query->where('estado', $this->filtroEstado);
            }

            if ($this->filtroUbicacionId) {
                $query->where('ubicacion_id', $this->filtroUbicacionId);
            }

            $equipos = $query->latest()->get();

            // Total general y disponibles para el contador de la categoría
            $totalCategoria = Equipo::where('categoria_id', $cat->id)->count();
            $disponiblesCategoria = Equipo::where('categoria_id', $cat->id)->where('estado', 'disponible')->count();

            // Si hay búsqueda y hay coincidencias, asegurar que el acordeón esté expandido
            if ($termino !== '' && $equipos->isNotEmpty() && !in_array($cat->id, $this->categoriasAbiertas, true)) {
                $this->categoriasAbiertas[] = $cat->id;
            }

            // Omitir categorías vacías si se está aplicando una búsqueda activa
            if ($termino !== '' && $equipos->isEmpty()) {
                continue;
            }

            $resultado[] = [
                'categoria' => $cat,
                'equipos' => $equipos,
                'disponibles' => $disponiblesCategoria,
                'total' => $totalCategoria,
            ];
        }

        return $resultado;
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
        if (!$this->categoria_id) {
            $this->categoria_id = Categoria::orderBy('orden')->value('id');
        }
        $this->fotografia = null;
        $this->mostrarModalCrear = true;
    }

    public function guardarEquipo(EquipoService $equipoService): void
    {
        Gate::authorize('create', Equipo::class);

        $this->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'fotografia' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'categoria_id.required' => 'Debe seleccionar una categoría institucional.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
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
                'categoria_id' => (int) $this->categoria_id,
                'ubicacion_id' => (int) $this->ubicacion_id,
                'fotografia' => $this->fotografia,
            ], Auth::user());

            // Abrir automáticamente el acordeón de la categoría donde se registró el activo
            if ($this->categoria_id && !in_array($this->categoria_id, $this->categoriasAbiertas, true)) {
                $this->categoriasAbiertas[] = (int) $this->categoria_id;
            }

            $this->mostrarModalCrear = false;
            session()->flash('mensaje', 'Equipo registrado exitosamente con ficha técnica categorizada.');
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

    public function abrirHojaVida(int $equipoId): void
    {
        $this->equipoHojaVidaId = $equipoId;
        $this->mostrarModalHojaVida = true;
    }

    public function equipoHojaVida(): ?Equipo
    {
        if (!$this->equipoHojaVidaId) {
            return null;
        }

        return Equipo::with([
            'ubicacion.carrera',
            'categoria',
            'documentos',
            'mantenimientos' => function ($q) {
                $q->with(['proveedor', 'documentos'])->orderByDesc('fecha_programada');
            },
        ])->find($this->equipoHojaVidaId);
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado con Identidad INACAP -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Catálogo de Equipos y Fichas Técnicas</h1>
                <p class="text-sm text-gray-500 mt-1">Gestión de activos agrupados por categorías especializadas, ciclo de vida institucional y hoja de vida técnica.</p>
            </div>
            <div class="flex items-center space-x-3">
                @if(Auth::user()->rol !== 'docente')
                    <button wire:click="abrirModalCrear" class="inline-flex items-center px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-md shadow-sm transition">
                        <svg class="w-5 h-5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Nuevo Equipo
                    </button>
                @endif
            </div>
        </div>

        @if(session()->has('mensaje'))
            <div class="p-4 bg-green-50 border-l-4 border-green-600 text-green-800 text-sm rounded-r-md">
                {{ session('mensaje') }}
            </div>
        @endif

        <!-- Filtros y Búsqueda Global -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Buscar</label>
                    <input wire:model.live.debounce.300ms="buscar" type="text" placeholder="Buscar categoría, equipo o serie..." class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Categoría</label>
                    <select wire:model.live="filtroCategoriaId" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                        <option value="">Todas las categorías</option>
                        @foreach($this->categorias() as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Estado</label>
                    <select wire:model.live="filtroEstado" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                        <option value="">Todos los estados</option>
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

            <!-- Botones de Acción Rápida para Acordeones -->
            <div class="flex items-center justify-between pt-2 border-t border-gray-100 text-xs text-gray-500">
                <div>
                    Mostrando equipos clasificados en las 7 áreas tecnológicas de pañol.
                </div>
                <div class="flex items-center space-x-3">
                    <button wire:click="expandirTodas" class="text-red-700 hover:text-red-900 font-medium transition">
                        Expandir todas
                    </button>
                    <span>|</span>
                    <button wire:click="colapsarTodas" class="text-gray-600 hover:text-gray-900 font-medium transition">
                        Colapsar todas
                    </button>
                </div>
            </div>
        </div>

        <!-- Lista de Acordeones por Categoría -->
        <div class="space-y-4">
            @php $grupos = $this->gruposCategorias(); @endphp

            @forelse($grupos as $grupo)
                @php
                    $cat = $grupo['categoria'];
                    $equipos = $grupo['equipos'];
                    $abierto = in_array($cat->id, $categoriasAbiertas, true);
                @endphp

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden transition-all duration-200">
                    <!-- Cabecera de Acordeón -->
                    <button wire:click="toggleCategoria({{ $cat->id }})" type="button" class="w-full px-5 py-4 flex items-center justify-between bg-white hover:bg-gray-50/80 transition text-left focus:outline-none">
                        <div class="flex items-center space-x-3">
                            <!-- Indicador Chevron rotatorio -->
                            <svg class="w-5 h-5 text-gray-400 transform transition-transform duration-200 {{ $abierto ? 'rotate-90 text-red-700' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>

                            <!-- Icono descriptivo de la categoría -->
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $abierto ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                                @if($cat->slug === 'computo-movil')
                                    <!-- Laptop -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                @elseif($cat->slug === 'redes-telecomunicaciones')
                                    <!-- Server / Network -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                @elseif($cat->slug === 'instrumentacion-electronica')
                                    <!-- Activity / Oscilloscope -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                @elseif($cat->slug === 'iot-robotica')
                                    <!-- Chip / Microcontroller -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                                @elseif($cat->slug === 'impresion-captura')
                                    <!-- Printer / Barcode -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                @elseif($cat->slug === 'herramientas-taller')
                                    <!-- Wrench / Tools -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
                                @else
                                    <!-- Video Camera / VR -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                @endif
                            </div>

                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ $cat->nombre }}</h3>
                                @if($cat->descripcion)
                                    <p class="text-xs text-gray-500">{{ $cat->descripcion }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Contador de Disponibilidad -->
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                <span class="text-green-700 font-bold mr-1">{{ $grupo['disponibles'] }} disponibles</span> / {{ $grupo['total'] }} total
                            </span>
                        </div>
                    </button>

                    <!-- Tabla de Equipos Expandible -->
                    @if($abierto)
                        <div class="border-t border-gray-200 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                                    <tr>
                                        <th class="px-6 py-3 text-left">Código / Barras</th>
                                        <th class="px-6 py-3 text-left">Nombre Estandarizado</th>
                                        <th class="px-6 py-3 text-left">Marca / Modelo</th>
                                        <th class="px-6 py-3 text-left">Estado</th>
                                        <th class="px-6 py-3 text-left">Ubicación</th>
                                        <th class="px-6 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse($equipos as $eq)
                                        <tr class="hover:bg-gray-50/75 transition">
                                            <td class="px-6 py-4 whitespace-nowrap font-mono font-medium text-gray-900">
                                                {{ $eq->codigo_barras }}
                                            </td>
                                            <td class="px-6 py-4 font-medium text-gray-900">
                                                {{ $eq->nombre }}
                                                <div class="text-xs text-gray-500 font-normal">S/N: {{ $eq->numero_serie }}</div>
                                            </td>
                                            <td class="px-6 py-4 text-gray-600">
                                                {{ $eq->marca }}
                                                <div class="text-xs text-gray-500">{{ $eq->modelo }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <x-status-badge :estado="$eq->estado" />
                                            </td>
                                            <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                                                {{ $eq->ubicacion?->nombre }}
                                            </td>
                                            <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                                <button wire:click="abrirHojaVida({{ $eq->id }})" type="button" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium inline-flex items-center">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    Hoja de Vida
                                                </button>
                                                @if($eq->estado !== 'dado_de_baja')
                                                    <button wire:click="abrirModalFalla({{ $eq->id }})" type="button" class="text-xs text-orange-600 hover:text-orange-900 font-medium inline-flex items-center">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                        Reportar Falla
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-6 py-6 text-center text-gray-500 text-sm">
                                                No hay equipos registrados bajo esta categoría que cumplan con los filtros actuales.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-100 text-center text-gray-500">
                    No se encontraron activos tecnológicos que coincidan con la búsqueda.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal Registro de Ficha Técnica de Equipo -->
    @if($mostrarModalCrear)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Registrar Ficha Técnica de Equipo</h3>
                    <button wire:click="$set('mostrarModalCrear', false)" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Nombre Estandarizado</label>
                        <input wire:model="nombre" type="text" placeholder="Ej: Computador de Escritorio All-in-One Intel Core i7 - Lab Redes" class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">
                        <p class="text-xs text-gray-500 mt-1">Debe ser descriptivo. Prohibidas siglas como PC-01, SW-L2.</p>
                        @error('nombre') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Código de Barras</label>
                            <input wire:model="codigo_barras" type="text" class="w-full text-sm rounded-md border-gray-300 font-mono focus:border-red-500 focus:ring-red-500">
                            @error('codigo_barras') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Número de Serie</label>
                            <input wire:model="numero_serie" type="text" placeholder="SN-12345678" class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">
                            @error('numero_serie') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Marca</label>
                            <input wire:model="marca" type="text" placeholder="Ej: Cisco, HP, Dell, Lenovo" class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">
                            @error('marca') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Modelo</label>
                            <input wire:model="modelo" type="text" placeholder="Ej: Catalyst 2960-X, ThinkPad T14" class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">
                            @error('modelo') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Categoría del Equipo</label>
                            <select wire:model="categoria_id" class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">
                                @foreach($this->categorias() as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                                @endforeach
                            </select>
                            @error('categoria_id') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Ubicación / Laboratorio</label>
                            <select wire:model="ubicacion_id" class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">
                                @foreach($this->ubicaciones() as $ub)
                                    <option value="{{ $ub->id }}">{{ $ub->nombre }} ({{ $ub->carrera?->codigo }})</option>
                                @endforeach
                            </select>
                            @error('ubicacion_id') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Fotografía del Equipo</label>
                        <input wire:model="fotografia" type="file" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        @error('fotografia') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalCrear', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="guardarEquipo" type="button" class="px-4 py-2 text-sm bg-red-700 hover:bg-red-800 text-white font-medium rounded-md shadow-sm transition">Guardar Equipo</button>
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
                    <button wire:click="$set('mostrarModalFalla', false)" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Descripción del Desperfecto</label>
                    <textarea wire:model="descripcionFalla" rows="4" placeholder="Describa con precisión los síntomas, errores o daños observados..." class="w-full text-sm rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500"></textarea>
                    @error('descripcionFalla') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                </div>

                <div class="p-3 bg-yellow-50 rounded text-xs text-yellow-800">
                    Al confirmar, el activo pasará automáticamente a estado <strong>En Mantenimiento</strong> y no podrá ser despachado a nuevos préstamos hasta su revisión técnica.
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalFalla', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="registrarFalla" type="button" class="px-4 py-2 text-sm bg-orange-600 hover:bg-orange-700 text-white font-medium rounded-md shadow-sm transition">Confirmar Reporte</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Hoja de Vida de Mantenimiento (Coincidente con Mockup 3) -->
    @if($mostrarModalHojaVida)
        @php
            $eqHv = $this->equipoHojaVida();
            $mantenimientos = $eqHv?->mantenimientos ?? collect();
            $totalMants = $mantenimientos->count();
            $ultimoPrev = $mantenimientos->where('tipo', 'preventivo')->whereNotNull('fecha_realizada')->sortByDesc('fecha_realizada')->first();
            $proximoProg = $mantenimientos->where('estado', 'programado')->where('fecha_programada', '>=', now()->toDateString())->sortBy('fecha_programada')->first();
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full p-6 space-y-6 max-h-[90vh] overflow-y-auto">
                <!-- Cabecera de la Hoja de Vida -->
                <div class="flex justify-between items-start border-b pb-4">
                    <div>
                        <div class="text-xs uppercase font-semibold text-gray-500 tracking-wider">Hoja de Vida de Mantenimiento</div>
                        <h2 class="text-2xl font-bold text-gray-900 mt-0.5">{{ $eqHv?->nombre ?? 'Activo Tecnológico' }}</h2>
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-2 text-xs text-gray-600">
                            <div>
                                <span class="font-semibold text-gray-500">Código:</span>
                                <span class="font-mono font-medium text-gray-900 ml-1">{{ $eqHv?->codigo_barras }}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-500">Serie:</span>
                                <span class="text-gray-900 ml-1">{{ $eqHv?->numero_serie }}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-500">Ubicación:</span>
                                <span class="text-gray-900 ml-1">{{ $eqHv?->ubicacion?->nombre }}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-500">Categoría:</span>
                                <span class="text-gray-900 ml-1">{{ $eqHv?->categoria?->nombre ?? 'General' }}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-500">Criticidad:</span>
                                @if($eqHv?->criticidad === 'alta')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 ml-1">Alta</span>
                                @elseif($eqHv?->criticidad === 'media')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 ml-1">Media</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 ml-1">Baja</span>
                                @endif
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-500 mr-1.5">Estado:</span>
                                <x-status-badge :estado="$eqHv?->estado" />
                            </div>
                        </div>
                    </div>
                    <button wire:click="$set('mostrarModalHojaVida', false)" class="text-gray-400 hover:text-gray-600 text-2xl font-bold leading-none p-1">&times;</button>
                </div>

                <!-- Tarjetas KPI de Resumen -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="text-xs font-semibold text-gray-500 uppercase">Total Mantenimientos</div>
                        <div class="text-3xl font-extrabold text-gray-900 mt-2">{{ $totalMants }}</div>
                        <div class="text-xs text-gray-500 mt-1">Intervenciones registradas</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="text-xs font-semibold text-gray-500 uppercase">Último Preventivo</div>
                        <div class="text-xl font-bold text-gray-900 mt-2">
                            {{ $ultimoPrev?->fecha_realizada ? $ultimoPrev->fecha_realizada->translatedFormat('d M Y') : 'Sin registros previos' }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $ultimoPrev ? ($ultimoPrev->subtipo ?? 'Preventivo') : 'A la espera de plan' }}
                        </div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="text-xs font-semibold text-gray-500 uppercase">Próximo Programado</div>
                        <div class="text-xl font-bold text-indigo-700 mt-2">
                            {{ $proximoProg?->fecha_programada ? $proximoProg->fecha_programada->translatedFormat('d M Y') : 'No programado' }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $proximoProg ? 'Según matriz técnica semestral' : 'Sin fecha asignada' }}
                        </div>
                    </div>
                </div>

                <!-- Historial Cronológico de Intervenciones y Fallas -->
                <div class="space-y-3">
                    <h3 class="text-base font-bold text-gray-900">Historial de Intervenciones y Fallas</h3>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                                    <tr>
                                        <th class="px-5 py-3 text-left">Fecha</th>
                                        <th class="px-5 py-3 text-left">Tipo</th>
                                        <th class="px-5 py-3 text-left">Descripción del Trabajo</th>
                                        <th class="px-5 py-3 text-left">Técnico / Responsable</th>
                                        <th class="px-5 py-3 text-left">Estado Final</th>
                                        <th class="px-5 py-3 text-center">Evidencia / Acta</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @forelse($mantenimientos as $mant)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-900 font-medium">
                                                {{ ($mant->fecha_realizada ?? $mant->fecha_programada)?->translatedFormat('d M Y') }}
                                            </td>
                                            <td class="px-5 py-3.5 whitespace-nowrap">
                                                @if($mant->tipo === 'preventivo')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                                        Preventivo
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                                        Correctivo
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3.5 text-gray-800 max-w-xs">
                                                <div class="font-medium">
                                                    {{ App\Models\Mantenimiento::SUBTIPOS[$mant->subtipo]['nombre'] ?? ($mant->subtipo ?? 'Mantenimiento General') }}
                                                </div>
                                                @if($mant->observaciones)
                                                    <div class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $mant->observaciones }}</div>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-600">
                                                {{ $mant->proveedor?->razon_social ?? 'Personal Técnico Pañol' }}
                                            </td>
                                            <td class="px-5 py-3.5 whitespace-nowrap">
                                                @if($mant->estado === 'realizado')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">Exitoso</span>
                                                @elseif($mant->estado === 'programado')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">Programado</span>
                                                @elseif($mant->estado === 'en_proceso')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">En Proceso</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800">{{ ucfirst($mant->estado) }}</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3.5 whitespace-nowrap text-center">
                                                @if($mant->documentos && $mant->documentos->isNotEmpty())
                                                    @php $doc = $mant->documentos->first(); @endphp
                                                    <a href="{{ asset('storage/' . $doc->ruta) }}" target="_blank" download class="inline-flex items-center p-1.5 text-gray-600 hover:text-red-700 rounded hover:bg-gray-100 transition" title="Descargar Acta">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    </a>
                                                @else
                                                    <span class="text-xs text-gray-400" title="Sin acta digitalizada">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-5 py-6 text-center text-gray-500 text-xs">
                                                No existen registros de mantenimiento o fallas previas para este equipo.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t pt-4">
                    <button wire:click="$set('mostrarModalHojaVida', false)" type="button" class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-md transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
