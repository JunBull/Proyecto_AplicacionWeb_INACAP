<?php

use App\Models\Asignatura;
use App\Models\Carrera;
use App\Models\Presupuesto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $buscar = '';
    public int $anioPresupuesto;

    // Modal Carrera
    public bool $mostrarModalCarrera = false;
    public ?int $carreraId = null;
    public string $codigoCarrera = '';
    public string $nombreCarrera = '';
    public bool $activaCarrera = true;

    // Modal Presupuesto Anual
    public bool $mostrarModalPresupuesto = false;
    public ?int $carreraPresupuestoId = null;
    public string $carreraPresupuestoNombre = '';
    public float $montoPresupuesto = 0;
    public float $saldoPresupuesto = 0;

    // Modal / Panel Asignaturas
    public bool $mostrarModalAsignaturas = false;
    public ?int $carreraAsignaturasId = null;
    public string $carreraAsignaturasNombre = '';
    public string $nuevoCodigoAsig = '';
    public string $nuevoNombreAsig = '';

    public function mount(): void
    {
        if (Auth::user()->rol !== 'administrador') {
            abort(403, 'Acceso restringido exclusivamente a Administradores.');
        }
        $this->anioPresupuesto = (int) date('Y');
    }

    public function carreras()
    {
        $query = Carrera::with(['asignaturas', 'presupuestos' => function ($q) {
            $q->where('anio', $this->anioPresupuesto);
        }])->orderBy('nombre');

        if (trim($this->buscar) !== '') {
            $t = '%' . trim($this->buscar) . '%';
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'LIKE', $t)
                  ->orWhere('codigo', 'LIKE', $t);
            });
        }

        return $query->get();
    }

    public function abrirModalCarrera(): void
    {
        $this->resetValidation();
        $this->carreraId = null;
        $this->codigoCarrera = '';
        $this->nombreCarrera = '';
        $this->activaCarrera = true;
        $this->mostrarModalCarrera = true;
    }

    public function guardarCarrera(): void
    {
        $rules = [
            'codigoCarrera' => [
                'required',
                'string',
                'max:30',
                $this->carreraId ? Rule::unique('carreras', 'codigo')->ignore($this->carreraId) : Rule::unique('carreras', 'codigo'),
            ],
            'nombreCarrera' => 'required|string|max:150',
            'activaCarrera' => 'boolean',
        ];

        $this->validate($rules, [
            'codigoCarrera.required' => 'El código de la carrera es obligatorio.',
            'codigoCarrera.unique' => 'Este código de carrera ya existe en el catálogo.',
            'nombreCarrera.required' => 'El nombre de la carrera es obligatorio.',
        ]);

        if ($this->carreraId) {
            $carrera = Carrera::findOrFail($this->carreraId);
            $carrera->update([
                'codigo' => strtoupper(trim($this->codigoCarrera)),
                'nombre' => trim($this->nombreCarrera),
                'activa' => $this->activaCarrera,
            ]);
        } else {
            Carrera::create([
                'codigo' => strtoupper(trim($this->codigoCarrera)),
                'nombre' => trim($this->nombreCarrera),
                'activa' => $this->activaCarrera,
            ]);
        }

        $this->mostrarModalCarrera = false;
        session()->flash('mensaje', 'Carrera académica guardada correctamente.');
    }

    public function abrirModalPresupuesto(int $carreraId): void
    {
        $carrera = Carrera::findOrFail($carreraId);
        $this->carreraPresupuestoId = $carrera->id;
        $this->carreraPresupuestoNombre = $carrera->nombre;

        $presupuesto = Presupuesto::where('carrera_id', $carrera->id)
            ->where('anio', $this->anioPresupuesto)
            ->first();

        $this->montoPresupuesto = $presupuesto ? (float) $presupuesto->monto_asignado : 0;
        $this->saldoPresupuesto = $presupuesto ? (float) $presupuesto->saldo_actual : 0;
        $this->mostrarModalPresupuesto = true;
    }

    public function guardarPresupuesto(): void
    {
        $this->validate([
            'montoPresupuesto' => 'required|numeric|min:0',
        ], [
            'montoPresupuesto.required' => 'Debe ingresar un monto presupuestario.',
            'montoPresupuesto.min' => 'El presupuesto no puede ser negativo.',
        ]);

        $presupuesto = Presupuesto::where('carrera_id', $this->carreraPresupuestoId)
            ->where('anio', $this->anioPresupuesto)
            ->first();

        if ($presupuesto) {
            $gastado = max(0, (float) $presupuesto->monto_asignado - (float) $presupuesto->saldo_actual);
            $presupuesto->update([
                'monto_asignado' => $this->montoPresupuesto,
                'saldo_actual' => max(0, $this->montoPresupuesto - $gastado),
            ]);
        } else {
            Presupuesto::create([
                'carrera_id' => $this->carreraPresupuestoId,
                'anio' => $this->anioPresupuesto,
                'monto_asignado' => $this->montoPresupuesto,
                'saldo_actual' => $this->montoPresupuesto,
            ]);
        }

        $this->mostrarModalPresupuesto = false;
        session()->flash('mensaje', "Presupuesto {$this->anioPresupuesto} asignado a {$this->carreraPresupuestoNombre}.");
    }

    public function abrirModalAsignaturas(int $carreraId): void
    {
        $this->resetValidation();
        $carrera = Carrera::with('asignaturas')->findOrFail($carreraId);
        $this->carreraAsignaturasId = $carrera->id;
        $this->carreraAsignaturasNombre = $carrera->nombre;
        $this->nuevoCodigoAsig = '';
        $this->nuevoNombreAsig = '';
        $this->mostrarModalAsignaturas = true;
    }

    public function agregarAsignatura(): void
    {
        $this->validate([
            'nuevoCodigoAsig' => 'required|string|max:30|unique:asignaturas,codigo',
            'nuevoNombreAsig' => 'required|string|max:150',
        ], [
            'nuevoCodigoAsig.required' => 'El código del ramo/taller es obligatorio.',
            'nuevoCodigoAsig.unique' => 'Ya existe un taller o asignatura con este código.',
            'nuevoNombreAsig.required' => 'El nombre del ramo/taller es obligatorio.',
        ]);

        Asignatura::create([
            'carrera_id' => $this->carreraAsignaturasId,
            'codigo' => strtoupper(trim($this->nuevoCodigoAsig)),
            'nombre' => trim($this->nuevoNombreAsig),
            'activo' => true,
        ]);

        $this->nuevoCodigoAsig = '';
        $this->nuevoNombreAsig = '';
        session()->flash('mensaje_asig', 'Asignatura o taller incorporado al catálogo exitosamente.');
    }

    public function toggleAsignatura(int $asigId): void
    {
        $asig = Asignatura::findOrFail($asigId);
        $asig->activo = ! $asig->activo;
        $asig->save();
    }

    public function eliminarAsignatura(int $asigId): void
    {
        $asig = Asignatura::findOrFail($asigId);
        $asig->delete();
        session()->flash('mensaje_asig', 'Asignatura eliminada del catálogo.');
    }
}; ?>

<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Encabezado -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-red-100 text-red-800 border border-red-200">
                    Administración
                </span>
                <span class="text-xs text-slate-400 font-mono">v1.1</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 mt-1">Carreras, Presupuesto y Talleres</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Gestión de centros de costo, presupuesto anual por carrera y catálogo de asignaturas/talleres prácticos (Sección 3.1).</p>
        </div>
        <button wire:click="abrirModalCarrera" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-700 hover:bg-red-800 text-white font-semibold text-xs sm:text-sm rounded-lg shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva Carrera
        </button>
    </div>

    @if(session()->has('mensaje'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-600 text-emerald-800 text-xs sm:text-sm rounded-r-lg">
            {{ session('mensaje') }}
        </div>
    @endif

    <!-- Filtros de Año y Búsqueda -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div class="w-full sm:w-80 relative">
            <input wire:model.live.debounce.300ms="buscar" type="text" placeholder="Buscar por código o nombre de carrera..." class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600 pl-9">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs font-semibold text-slate-600">Año Presupuestario:</label>
            <select wire:model.live="anioPresupuesto" class="text-xs sm:text-sm rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                @for($y = (int)date('Y') + 1; $y >= 2024; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    <!-- Grilla de Carreras -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($this->carreras() as $carrera)
            @php
                $presupuestoActual = $carrera->presupuestos->first();
                $asignado = $presupuestoActual ? (float) $presupuestoActual->monto_asignado : 0;
                $saldo = $presupuestoActual ? (float) $presupuestoActual->saldo_actual : 0;
                $gastado = max(0, $asignado - $saldo);
                $pctGastado = $asignado > 0 ? min(100, round(($gastado / $asignado) * 100)) : 0;
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between hover:shadow-md transition">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $carrera->codigo }}
                            </span>
                            <h3 class="text-base font-bold text-slate-900 mt-1 leading-snug">{{ $carrera->nombre }}</h3>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $carrera->activa ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                            {{ $carrera->activa ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>

                    <!-- Métricas de Presupuesto y Talleres -->
                    <div class="space-y-2 pt-2 border-t border-slate-100 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="p-2.5 bg-emerald-50/70 border border-emerald-100 rounded-lg">
                                <div class="text-[10px] font-bold text-emerald-800 uppercase flex items-center justify-between">
                                    <span>Saldo Disponible</span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                </div>
                                <div class="text-sm font-extrabold text-emerald-700 mt-0.5">
                                    ${{ number_format($saldo, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="p-2.5 bg-slate-50 border border-slate-100 rounded-lg">
                                <div class="text-[10px] font-bold text-slate-400 uppercase">Asignado {{ $anioPresupuesto }}</div>
                                <div class="text-sm font-extrabold text-slate-900 mt-0.5">
                                    ${{ number_format($asignado, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        <!-- Barra de Ejecución y Consumo -->
                        <div class="p-2.5 bg-slate-50 rounded-lg space-y-1.5 border border-slate-100">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-medium">Ejecutado (Compras/Facturas): <strong class="text-slate-800">${{ number_format($gastado, 0, ',', '.') }}</strong></span>
                                <span class="font-bold {{ $pctGastado > 85 ? 'text-red-600' : 'text-slate-700' }}">{{ $pctGastado }}%</span>
                            </div>
                            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-red-600 h-1.5 rounded-full transition-all" style="width: {{ $pctGastado }}%"></div>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                                <span>Talleres / Asignaturas: <strong class="text-slate-700">{{ $carrera->asignaturas->count() }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center gap-2 pt-4 mt-2 border-t border-slate-100">
                    <button wire:click="abrirModalPresupuesto({{ $carrera->id }})" class="flex-1 py-1.5 px-3 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition text-center">
                        Presupuesto
                    </button>
                    <button wire:click="abrirModalAsignaturas({{ $carrera->id }})" class="flex-1 py-1.5 px-3 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition text-center">
                        Catálogo Talleres
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-3 bg-white p-8 rounded-xl border border-slate-200 text-center text-slate-400">
                No se encontraron carreras registradas en el sistema.
            </div>
        @endforelse
    </div>

    <!-- Modal Presupuesto Anual -->
    @if($mostrarModalPresupuesto)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4 border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Asignación Presupuestaria Anual</h2>
                        <p class="text-xs text-slate-500 font-medium">{{ $carreraPresupuestoNombre }} (Año {{ $anioPresupuesto }})</p>
                    </div>
                    <button wire:click="$set('mostrarModalPresupuesto', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                @if($carreraPresupuestoId)
                    @php
                        $presupuestoModal = \App\Models\Presupuesto::where('carrera_id', $carreraPresupuestoId)->where('anio', $anioPresupuesto)->first();
                        $saldoModal = $presupuestoModal ? (float) $presupuestoModal->saldo_actual : 0;
                        $asignadoModal = $presupuestoModal ? (float) $presupuestoModal->monto_asignado : 0;
                        $gastadoModal = max(0, $asignadoModal - $saldoModal);
                    @endphp
                    @if($presupuestoModal)
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500 font-medium">Saldo vigente disponible:</span>
                                <strong class="text-sm font-bold text-emerald-700">${{ number_format($saldoModal, 0, ',', '.') }}</strong>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500 font-medium">Gasto ejecutado (Facturas/Compras):</span>
                                <strong class="text-slate-800">${{ number_format($gastadoModal, 0, ',', '.') }}</strong>
                            </div>
                        </div>
                    @endif
                @endif

                <div>
                    <label class="block font-semibold text-slate-700 text-xs sm:text-sm mb-1">Monto Asignado ($ CLP sin decimales) *</label>
                    <input wire:model="montoPresupuesto" type="number" min="0" step="1" onkeydown="if(event.key === '.' || event.key === ',' || event.key === 'e' || event.key === 'E' || event.key === '-') event.preventDefault();" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full text-sm rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                    @error('montoPresupuesto') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                    <p class="text-[11px] text-slate-400 mt-1">Este monto rige las compras y cotizaciones aprobadas para esta carrera durante el año {{ $anioPresupuesto }}.</p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-3">
                    <button wire:click="$set('mostrarModalPresupuesto', false)" type="button" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">
                        Cancelar
                    </button>
                    <button wire:click="guardarPresupuesto" type="button" class="px-5 py-2 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg shadow-sm">
                        Guardar Presupuesto
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Catálogo de Asignaturas / Talleres (CP-23) -->
    @if($mostrarModalAsignaturas)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 space-y-4 border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Catálogo de Asignaturas y Talleres</h2>
                        <p class="text-xs text-slate-500 font-medium">{{ $carreraAsignaturasNombre }}</p>
                    </div>
                    <button wire:click="$set('mostrarModalAsignaturas', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                @if(session()->has('mensaje_asig'))
                    <div class="p-3 bg-emerald-50 border-l-4 border-emerald-600 text-emerald-800 text-xs rounded-r-lg">
                        {{ session('mensaje_asig') }}
                    </div>
                @endif

                <!-- Formulario Agregar Asignatura -->
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    <div class="text-xs font-bold text-slate-700">Agregar Nueva Asignatura / Taller</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <input wire:model="nuevoCodigoAsig" type="text" placeholder="Ej. INF-101" class="w-full text-xs rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                            @error('nuevoCodigoAsig') <span class="text-[10px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2 flex gap-2">
                            <input wire:model="nuevoNombreAsig" type="text" placeholder="Nombre de Asignatura o Taller Práctico" class="flex-1 text-xs rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                            <button wire:click="agregarAsignatura" type="button" class="px-4 py-1.5 bg-red-700 hover:bg-red-800 text-white font-semibold text-xs rounded-lg shadow-sm">
                                Agregar
                            </button>
                        </div>
                    </div>
                    @error('nuevoNombreAsig') <span class="text-[10px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                </div>

                <!-- Lista de Asignaturas -->
                <div class="max-h-60 overflow-y-auto divide-y divide-slate-100 text-xs">
                    @php
                        $asignaturasList = Asignatura::where('carrera_id', $carreraAsignaturasId)->orderBy('codigo')->get();
                    @endphp
                    @forelse($asignaturasList as $asig)
                        <div class="py-2.5 flex items-center justify-between gap-3">
                            <div>
                                <span class="font-mono font-bold text-slate-900">{{ $asig->codigo }}</span>
                                <span class="text-slate-700 ms-1">{{ $asig->nombre }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="toggleAsignatura({{ $asig->id }})" class="px-2 py-0.5 rounded text-[10px] font-bold {{ $asig->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $asig->activo ? 'Activa' : 'Inactiva' }}
                                </button>
                                <button wire:click="eliminarAsignatura({{ $asig->id }})" class="text-red-600 hover:text-red-800 font-bold px-2 py-0.5" title="Eliminar">&times;</button>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-400">
                            No hay asignaturas registradas para esta carrera.
                        </div>
                    @endforelse
                </div>

                <div class="flex items-center justify-end border-t border-slate-100 pt-3">
                    <button wire:click="$set('mostrarModalAsignaturas', false)" type="button" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
