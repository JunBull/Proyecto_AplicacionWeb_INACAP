<?php

use App\Models\BajaReemplazo;
use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Mantenimiento;
use App\Models\Proveedor;
use App\Services\BajaReemplazoService;
use App\Services\FallaService;
use App\Services\MantenimientoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, WithPagination;

    // Pestaña activa: 'preventivo', 'fallas'
    public string $pestanaActiva = 'preventivo';

    // Subfiltros internos
    public string $subfiltroPreventivo = 'activos'; // 'activos', 'completados'
    public string $subfiltroFallas = 'pendientes';   // 'pendientes', 'resueltas'

    // Modal Programar (existente)
    public bool $mostrarModalProgramar = false;
    public ?int $equipo_id = null;
    public ?int $proveedor_id = null;
    public string $tipo = 'preventivo';
    public string $frecuencia = 'semestral';
    public string $fecha_programada = '';
    public string $observaciones = '';

    // Modal Ejecutar / Veredicto técnico
    public bool $mostrarModalEjecutar = false;
    public ?int $mantenimientoId = null;
    public string $fecha_realizada = '';
    public string $observaciones_ejecucion = '';
    public $informe_tecnico = null;
    public string $veredicto = 'conforme';
    public string $falla_descripcion = '';
    public string $baja_tipo = 'baja';
    public string $baja_motivo = '';

    // Modal In-Situ Solicitar Baja
    public bool $mostrarModalBaja = false;
    public ?int $bajaEquipoId = null;
    public ?string $bajaEquipoNombre = null;
    public string $bajaTipo = 'baja';
    public string $bajaMotivo = '';

    // Modal In-Situ Atender Falla
    public bool $mostrarModalAtender = false;
    public ?int $fallaAtenderId = null;
    public ?string $fallaRecursoNombre = null;
    public string $fallaNuevoEstado = 'resuelta';
    public string $fallaObservaciones = '';

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

    public function cambiarPestana(string $pestana): void
    {
        if ($pestana === 'historial') {
            $this->pestanaActiva = 'preventivo';
            $this->subfiltroPreventivo = 'completados';
            $this->resetPage('realizados_page');
            return;
        }

        if (in_array($pestana, ['preventivo', 'fallas'], true)) {
            $this->pestanaActiva = $pestana;
            $this->resetPage();
        }
    }

    public function cambiarSubfiltroPreventivo(string $subfiltro): void
    {
        if (in_array($subfiltro, ['activos', 'completados'], true)) {
            $this->subfiltroPreventivo = $subfiltro;
            $this->resetPage('programados_page');
            $this->resetPage('realizados_page');
        }
    }

    public function cambiarSubfiltroFallas(string $subfiltro): void
    {
        if (in_array($subfiltro, ['pendientes', 'resueltas'], true)) {
            $this->subfiltroFallas = $subfiltro;
            $this->resetPage('fallas_page');
        }
    }

    public function conteoPreventivoActivos(): int
    {
        return Mantenimiento::whereIn('estado', ['programado', 'vencido', 'en_proceso'])->count();
    }

    public function conteoPreventivoCompletados(): int
    {
        return Mantenimiento::where('estado', 'realizado')->count();
    }

    public function conteoFallasPendientes(): int
    {
        return Falla::whereIn('estado', ['reportada', 'en_revision', 'en_reparacion'])->count();
    }

    public function conteoFallasResueltas(): int
    {
        return Falla::whereIn('estado', ['resuelta', 'derivada_a_baja'])->count();
    }

    // Compatibilidad retroactiva para tests existentes
    public function mantenimientos()
    {
        return Mantenimiento::with(['equipo.ubicacion', 'proveedor', 'documentos'])
            ->latest('fecha_programada')
            ->get();
    }

    public function mantenimientosProgramados()
    {
        return Mantenimiento::whereIn('estado', ['programado', 'vencido', 'en_proceso'])
            ->with(['equipo.ubicacion', 'proveedor', 'documentos'])
            ->latest('fecha_programada')
            ->paginate(15, ['*'], 'programados_page');
    }

    public function mantenimientosRealizados()
    {
        return Mantenimiento::where('estado', 'realizado')
            ->with(['equipo.ubicacion', 'proveedor', 'documentos'])
            ->latest('fecha_realizada')
            ->paginate(15, ['*'], 'realizados_page');
    }

    public function fallasReportadas()
    {
        $query = Falla::with(['equipo.ubicacion', 'insumo.ubicacion', 'ubicacion', 'reportador', 'documentos'])
            ->latest('id');

        if ($this->subfiltroFallas === 'pendientes') {
            $query->whereIn('estado', ['reportada', 'en_revision', 'en_reparacion']);
        } else {
            $query->whereIn('estado', ['resuelta', 'derivada_a_baja']);
        }

        return $query->paginate(15, ['*'], 'fallas_page');
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
            'frecuencia' => 'required|in:semestral,anual',
            'fecha_programada' => 'required|date',
        ]);

        $equipo = Equipo::findOrFail($this->equipo_id);

        try {
            $servicio->programarMantenimiento(
                $equipo,
                'preventivo',
                $this->frecuencia,
                $this->fecha_programada,
                $this->proveedor_id ? (int) $this->proveedor_id : null,
                $this->observaciones
            );

            $this->mostrarModalProgramar = false;
            session()->flash('mensaje', 'Mantenimiento preventivo programado con éxito.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }
        }
    }

    public function iniciarMantenimiento(int $id, MantenimientoService $servicio): void
    {
        $mant = Mantenimiento::findOrFail($id);
        Gate::authorize('update', $mant);

        try {
            $servicio->iniciarMantenimiento($mant);
            session()->flash('mensaje', "Mantenimiento iniciado. El equipo {$mant->equipo?->codigo_barras} ha pasado a taller y se encuentra bloqueado para préstamos.");
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
        $this->veredicto = 'conforme';
        $this->falla_descripcion = '';
        $this->baja_tipo = 'baja';
        $this->baja_motivo = '';
        $this->mostrarModalEjecutar = true;
    }

    public function registrarEjecucion(MantenimientoService $servicio): void
    {
        $reglas = [
            'fecha_realizada' => 'required|date',
            'veredicto' => 'required|in:conforme,falla,baja',
            'informe_tecnico' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];

        $mensajes = [
            'informe_tecnico.mimes' => 'El informe técnico debe estar en formato PDF, JPG o PNG.',
            'informe_tecnico.max' => 'El archivo no debe exceder los 10 MB.',
        ];

        if ($this->veredicto === 'falla') {
            $reglas['falla_descripcion'] = 'required|string|min:10';
            $mensajes['falla_descripcion.required'] = 'Debe detallar el desperfecto detectado en las pruebas.';
            $mensajes['falla_descripcion.min'] = 'La descripción de la falla debe tener al menos 10 caracteres.';
        } elseif ($this->veredicto === 'baja') {
            $reglas['baja_tipo'] = 'required|in:baja,reemplazo';
            $reglas['baja_motivo'] = 'required|string|min:15';
            $mensajes['baja_motivo.required'] = 'Debe ingresar la justificación técnica de la baja o reemplazo.';
            $mensajes['baja_motivo.min'] = 'La justificación técnica debe contener al menos 15 caracteres.';
        }

        $this->validate($reglas, $mensajes);

        $mant = Mantenimiento::findOrFail($this->mantenimientoId);
        Gate::authorize('update', $mant);

        $servicio->registrarEjecucion(
            $mant,
            $this->fecha_realizada,
            $this->observaciones_ejecucion,
            $this->informe_tecnico,
            Auth::user(),
            $this->veredicto,
            [
                'descripcion_falla' => $this->falla_descripcion,
                'baja_tipo' => $this->baja_tipo,
                'baja_motivo' => $this->baja_motivo,
            ]
        );

        $this->mostrarModalEjecutar = false;

        $msg = match ($this->veredicto) {
            'conforme' => 'Mantenimiento registrado como realizado y activo reintegrado a disponible.',
            'falla' => 'Mantenimiento concluido con novedad técnica registrada. El activo continúa fuera de servicio en taller.',
            'baja' => 'Mantenimiento concluido e informe derivado a propuesta de baja para revisión del Administrador.',
        };

        session()->flash('mensaje', $msg);
    }

    public function abrirModalSolicitarBaja(int $equipoId): void
    {
        $equipo = Equipo::findOrFail($equipoId);
        $this->resetErrorBag();
        $this->bajaEquipoId = $equipo->id;
        $this->bajaEquipoNombre = $equipo->nombre . ' (' . ($equipo->codigo_barras ?? 'S/C') . ')';
        $this->bajaTipo = 'baja';
        $this->bajaMotivo = '';
        $this->mostrarModalBaja = true;
    }

    public function confirmarSolicitudBaja(BajaReemplazoService $servicio): void
    {
        $this->validate([
            'bajaEquipoId' => 'required|exists:equipos,id',
            'bajaTipo' => 'required|in:baja,reemplazo',
            'bajaMotivo' => 'required|string|min:15',
        ], [
            'bajaMotivo.required' => 'Debe ingresar la justificación técnica de la propuesta.',
            'bajaMotivo.min' => 'La justificación técnica debe contener al menos 15 caracteres.',
        ]);

        $equipo = Equipo::findOrFail($this->bajaEquipoId);
        $servicio->proponerBajaOReemplazo(
            $equipo,
            Auth::user(),
            $this->bajaTipo,
            $this->bajaMotivo
        );

        $this->mostrarModalBaja = false;
        session()->flash('mensaje', 'Propuesta de ' . $this->bajaTipo . ' enviada exitosamente para revisión administrativa.');
    }

    public function recepcionarFalla(int $id, FallaService $servicio): void
    {
        $falla = Falla::findOrFail($id);
        Gate::authorize('update', $falla);

        $servicio->atenderFalla(
            $falla,
            Auth::user(),
            'en_revision',
            'Recepción e inicio de diagnóstico técnico en pañol.'
        );

        session()->flash('mensaje', 'Incidencia #INC-' . str_pad($falla->id, 5, '0', STR_PAD_LEFT) . ' recepcionada en pañol. Equipo en diagnóstico.');
    }

    public function abrirModalAtenderFalla(int $fallaId): void
    {
        $falla = Falla::with(['equipo', 'insumo'])->findOrFail($fallaId);
        $this->resetErrorBag();
        $this->fallaAtenderId = $falla->id;
        $this->fallaRecursoNombre = $falla->equipo?->nombre ?? $falla->insumo?->nombre ?? 'Recurso';
        $this->fallaNuevoEstado = 'resuelta';
        $this->fallaObservaciones = '';
        $this->mostrarModalAtender = true;
    }

    public function confirmarAtencionFalla(FallaService $servicio): void
    {
        $this->validate([
            'fallaAtenderId' => 'required|exists:fallas,id',
            'fallaNuevoEstado' => 'required|in:en_revision,en_reparacion,resuelta,derivada_a_baja',
            'fallaObservaciones' => 'required|string|min:10',
        ], [
            'fallaObservaciones.required' => 'Debe ingresar las observaciones técnicas o diagnóstico.',
            'fallaObservaciones.min' => 'Las observaciones técnicas deben tener al menos 10 caracteres.',
        ]);

        $falla = Falla::findOrFail($this->fallaAtenderId);
        Gate::authorize('update', $falla);
        $servicio->atenderFalla(
            $falla,
            Auth::user(),
            $this->fallaNuevoEstado,
            $this->fallaObservaciones
        );

        $this->mostrarModalAtender = false;
        $msg = match ($this->fallaNuevoEstado) {
            'resuelta' => 'Incidencia resuelta exitosamente. Equipo reintegrado a disponible.',
            'derivada_a_baja' => 'Incidencia derivada a propuesta de baja para revisión del Administrador.',
            'en_reparacion' => 'Equipo registrado en reparación / servicio técnico externo.',
            default => 'Atención técnica de la falla actualizada.',
        };
        session()->flash('mensaje', $msg);
    }

    public function descargarDocumento(int $documentoId)
    {
        $documento = Documento::findOrFail($documentoId);
        if (!Storage::disk('public')->exists($documento->ruta)) {
            session()->flash('error', 'El archivo no se encuentra disponible en el almacenamiento.');
            return null;
        }

        return Storage::disk('public')->download($documento->ruta, $documento->nombre_original);
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado simple -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Mantenimientos</h1>
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

        @if(session()->has('error'))
            <div class="p-4 bg-red-50 border-l-4 border-red-600 text-red-800 text-sm rounded-r-md">
                {{ session('error') }}
            </div>
        @endif

        <!-- Barra de Pestañas Superiores (2 Pestañas Maestras) -->
        <div class="flex border-b border-gray-200 gap-2 bg-white px-4 pt-3 rounded-t-lg shadow-sm">
            <button 
                type="button" 
                wire:click="cambiarPestana('preventivo')" 
                class="pb-3 px-3 text-sm font-semibold transition border-b-2 flex items-center gap-2 {{ $pestanaActiva === 'preventivo' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Mantenimiento Preventivo
            </button>
            <button 
                type="button" 
                wire:click="cambiarPestana('fallas')" 
                class="pb-3 px-3 text-sm font-semibold transition border-b-2 flex items-center gap-2 {{ $pestanaActiva === 'fallas' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Equipos reportados con fallas
            </button>
        </div>

        {{-- ========================================================================= --}}
        {{-- PESTAÑA 1: MANTENIMIENTO PREVENTIVO                                      --}}
        {{-- ========================================================================= --}}
        @if($pestanaActiva === 'preventivo')
            <!-- Subfiltros Preventivo -->
            <div class="flex items-center gap-2">
                <button 
                    type="button" 
                    wire:click="cambiarSubfiltroPreventivo('activos')" 
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold transition {{ $subfiltroPreventivo === 'activos' ? 'bg-red-700 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}"
                >
                    <span>Activos / En Taller</span>
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] {{ $subfiltroPreventivo === 'activos' ? 'bg-red-900 text-white' : 'bg-gray-100 text-gray-700' }}">
                        {{ $this->conteoPreventivoActivos() }}
                    </span>
                </button>
                <button 
                    type="button" 
                    wire:click="cambiarSubfiltroPreventivo('completados')" 
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold transition {{ $subfiltroPreventivo === 'completados' ? 'bg-red-700 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}"
                >
                    <span>Completados / Historial</span>
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] {{ $subfiltroPreventivo === 'completados' ? 'bg-red-900 text-white' : 'bg-gray-100 text-gray-700' }}">
                        {{ $this->conteoPreventivoCompletados() }}
                    </span>
                </button>
            </div>

            @if($subfiltroPreventivo === 'activos')
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
                                    <div class="mt-1 text-gray-600">Fecha objetivo: <span class="font-mono">{{ \Carbon\Carbon::parse($alerta['fecha_programada'])->format('d-m-y') }}</span></div>
                                    <div class="font-medium mt-0.5 {{ $alerta['dias_restantes'] < 0 ? 'text-red-700 font-bold' : 'text-gray-700' }}">
                                        {{ $alerta['dias_restantes'] < 0 ? 'Atrasado por ' . abs($alerta['dias_restantes']) . ' día(s)' : 'Vence en ' . $alerta['dias_restantes'] . ' día(s)' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Tabla de Mantenimientos Activos / En Taller -->
                @php $programados = $this->mantenimientosProgramados(); @endphp
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                                <tr>
                                    <th class="px-6 py-3 text-left">Equipo</th>
                                    <th class="px-6 py-3 text-left">Tipo / Frecuencia</th>
                                    <th class="px-6 py-3 text-left">Criticidad</th>
                                    <th class="px-6 py-3 text-left">Fecha Programada</th>
                                    <th class="px-6 py-3 text-left">Estado</th>
                                    <th class="px-6 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($programados as $m)
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
                                            {{ $m->fecha_programada?->format('d-m-y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($m->estado === 'vencido')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">Vencido</span>
                                            @elseif($m->estado === 'en_proceso')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                                    <span class="w-1.5 h-1.5 mr-1.5 bg-blue-600 rounded-full animate-pulse"></span>
                                                    En Proceso
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">Programado</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            @if(Auth::user()->rol !== 'docente')
                                                @if($m->estado === 'en_proceso')
                                                    <button wire:click="abrirModalEjecutar({{ $m->id }})" class="text-xs font-medium text-white bg-green-700 hover:bg-green-800 px-3 py-1.5 rounded shadow-sm transition">
                                                        Finalizar Mantenimiento
                                                    </button>
                                                @else
                                                    <button wire:click="iniciarMantenimiento({{ $m->id }})" class="text-xs font-medium text-blue-700 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded border border-blue-200 transition">
                                                        Iniciar Mantenimiento
                                                    </button>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">No hay mantenimientos activos o pendientes en este momento.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($programados->hasPages())
                        <div class="px-6 py-3 border-t bg-gray-50">
                            {{ $programados->links() }}
                        </div>
                    @endif
                </div>
            @else
                <!-- Tabla de Mantenimientos Realizados (Historial) -->
                @php $realizados = $this->mantenimientosRealizados(); @endphp
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
                                @forelse($realizados as $m)
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
                                            {{ $m->fecha_programada?->format('d-m-y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-600">
                                            {{ $m->fecha_realizada ? $m->fecha_realizada->format('d-m-y') : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">Realizado</span>
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap space-x-2">
                                            @php $docMant = $m->documentos->first(); @endphp
                                            @if($docMant)
                                                <a href="{{ asset('storage/' . $docMant->ruta) }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-700 bg-blue-50 hover:bg-blue-100 font-medium px-2 py-1 rounded border border-blue-200 transition" title="Ver / Descargar Informe Adjunto">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                    Informe adjunto
                                                </a>
                                            @else
                                                <span class="text-xs text-gray-400">Sin adjunto</span>
                                            @endif

                                            @if(Auth::user()->rol !== 'docente' && $m->equipo_id)
                                                <button wire:click="abrirModalSolicitarBaja({{ $m->equipo_id }})" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-amber-800 bg-amber-50 hover:bg-amber-100 rounded border border-amber-300 transition" title="Solicitar baja o reemplazo de este equipo">
                                                    Solicitar Baja
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">No hay registros de mantenimientos realizados en el historial.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($realizados->hasPages())
                        <div class="px-6 py-3 border-t bg-gray-50">
                            {{ $realizados->links() }}
                        </div>
                    @endif
                </div>
            @endif
        @endif

        {{-- ========================================================================= --}}
        {{-- PESTAÑA 2: EQUIPOS REPORTADOS CON FALLAS (CP-28)                         --}}
        {{-- ========================================================================= --}}
        @if($pestanaActiva === 'fallas')
            <!-- Subfiltros Fallas -->
            <div class="flex items-center gap-2">
                <button 
                    type="button" 
                    wire:click="cambiarSubfiltroFallas('pendientes')" 
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold transition {{ $subfiltroFallas === 'pendientes' ? 'bg-red-700 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}"
                >
                    <span>Pendientes / En Taller</span>
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] {{ $subfiltroFallas === 'pendientes' ? 'bg-red-900 text-white' : 'bg-gray-100 text-gray-700' }}">
                        {{ $this->conteoFallasPendientes() }}
                    </span>
                </button>
                <button 
                    type="button" 
                    wire:click="cambiarSubfiltroFallas('resueltas')" 
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold transition {{ $subfiltroFallas === 'resueltas' ? 'bg-red-700 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}"
                >
                    <span>Resueltas / Historial</span>
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] {{ $subfiltroFallas === 'resueltas' ? 'bg-red-900 text-white' : 'bg-gray-100 text-gray-700' }}">
                        {{ $this->conteoFallasResueltas() }}
                    </span>
                </button>
            </div>

            @php $fallas = $this->fallasReportadas(); @endphp
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                            <tr>
                                <th class="px-6 py-3 text-left">Folio / Recurso</th>
                                <th class="px-6 py-3 text-left">Ubicación</th>
                                <th class="px-6 py-3 text-left">Reportado Por</th>
                                <th class="px-6 py-3 text-left">Descripción de la Novedad</th>
                                <th class="px-6 py-3 text-left">Estado</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($fallas as $f)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        <div class="font-bold text-xs text-slate-500 font-mono">#INC-{{ str_pad($f->id, 5, '0', STR_PAD_LEFT) }}</div>
                                        <div>{{ $f->equipo?->nombre ?? $f->insumo?->nombre ?? 'Recurso no identificado' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $f->equipo?->codigo_barras ?? $f->insumo?->codigo_barras ?? 'Sin código' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 whitespace-nowrap">
                                        {{ $f->ubicacion?->nombre ?? $f->equipo?->ubicacion?->nombre ?? 'Pañol Central' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        <div class="font-medium text-gray-900">{{ $f->reportador?->nombre ?? 'Docente' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $f->fecha_reporte?->format('d-m-y') }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 max-w-xs">
                                        <p class="line-clamp-2 text-xs" title="{{ $f->descripcion }}">{{ $f->descripcion }}</p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $estadoClases = [
                                                'reportada' => 'bg-amber-100 text-amber-800',
                                                'en_revision' => 'bg-blue-100 text-blue-800',
                                                'en_reparacion' => 'bg-purple-100 text-purple-800',
                                                'resuelta' => 'bg-emerald-100 text-emerald-800',
                                                'derivada_a_baja' => 'bg-red-100 text-red-800',
                                            ];
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $estadoClases[$f->estado] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst(str_replace('_', ' ', $f->estado)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap space-x-2">
                                        @php $docIncidencia = $f->documentos->first(); @endphp
                                        @if($docIncidencia)
                                            @php
                                                $esPdf = str_ends_with(strtolower($docIncidencia->nombre_original ?? $docIncidencia->ruta), '.pdf');
                                            @endphp
                                            <a href="{{ asset('storage/' . $docIncidencia->ruta) }}" target="_blank" class="inline-flex items-center gap-1 text-xs {{ $esPdf ? 'text-red-600 hover:text-red-800 bg-red-50 border-red-200 hover:bg-red-100' : 'text-blue-700 hover:text-blue-900 bg-blue-50 border-blue-200 hover:bg-blue-100' }} font-medium px-2 py-1 rounded border transition" title="Ver / Descargar {{ $esPdf ? 'Informe PDF' : 'Archivo Adjunto' }}">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                {{ $esPdf ? 'Ver PDF' : 'Ver Adjunto' }}
                                            </a>
                                        @endif
                                        @if(Auth::user()->rol !== 'docente')
                                            @if($f->estado === 'reportada')
                                                <button wire:click="recepcionarFalla({{ $f->id }})" class="inline-flex items-center text-xs font-medium text-blue-700 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-2.5 py-1 rounded border border-blue-200 transition" title="Recepcionar recurso en pañol e iniciar diagnóstico">
                                                    Recepcionar en Pañol
                                                </button>
                                            @elseif(in_array($f->estado, ['en_revision', 'en_reparacion']))
                                                <button wire:click="abrirModalAtenderFalla({{ $f->id }})" class="inline-flex items-center text-xs font-medium text-white bg-green-700 hover:bg-green-800 px-2.5 py-1 rounded shadow-sm transition" title="Registrar resolución técnica de la incidencia">
                                                    Resolver Incidencia
                                                </button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">No se registran incidencias técnicas o fallas en este subfiltro.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($fallas->hasPages())
                    <div class="px-6 py-3 border-t bg-gray-50">
                        {{ $fallas->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Modal Programar Mantenimiento -->
    @if($mostrarModalProgramar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Programar Mantenimiento</h3>
                    <button wire:click="$set('mostrarModalProgramar', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Equipo a Intervenir</label>
                        <select wire:model="equipo_id" class="w-full text-sm rounded-md border-gray-300">
                            @foreach($this->equiposDisponibles() as $e)
                                <option value="{{ $e->id }}">[{{ $e->codigo_barras ?? 'S/C' }}] {{ $e->nombre }}</option>
                            @endforeach
                        </select>
                        @error('equipo_id') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Tipo de Mantenimiento</label>
                            <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-xs font-semibold text-gray-700 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                                Preventivo Periódico
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Periodicidad</label>
                            <select wire:model="frecuencia" class="w-full text-sm rounded-md border-gray-300">
                                <option value="semestral">Semestral (6 meses)</option>
                                <option value="anual">Anual (12 meses)</option>
                            </select>
                            @error('frecuencia') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
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

    <!-- Modal Finalizar Mantenimiento / Registrar Ejecución -->
    @if($mostrarModalEjecutar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Finalizar Mantenimiento Técnico</h3>
                    <button wire:click="$set('mostrarModalEjecutar', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Fecha de Ejecución</label>
                            <input wire:model="fecha_realizada" type="date" class="w-full text-sm rounded-md border-gray-300">
                            @error('fecha_realizada') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Veredicto Operativo</label>
                            <select wire:model.live="veredicto" class="w-full text-sm rounded-md border-gray-300 font-medium">
                                <option value="conforme">🟢 Operativo / Conforme</option>
                                <option value="falla">🟡 Falla Detectada / Mantener en Taller</option>
                                <option value="baja">🔴 Daño Irreparable / Proponer Baja</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Detalle de Trabajos Realizados</label>
                        <textarea wire:model="observaciones_ejecucion" rows="2" class="w-full text-sm rounded-md border-gray-300" placeholder="Limpieza, calibración, lubricación, ajuste de componentes..."></textarea>
                    </div>

                    @if($veredicto === 'falla')
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-md space-y-2">
                            <label class="block text-xs font-bold text-amber-900 uppercase">Detalle del Desperfecto / Falla Detectada</label>
                            <textarea wire:model="falla_descripcion" rows="2" class="w-full text-sm rounded-md border-amber-300 bg-white" placeholder="Describa la falla técnica detectada que impide reintegrar el equipo..."></textarea>
                            @error('falla_descripcion') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                            <p class="text-[11px] text-amber-700">El equipo permanecerá en <strong>en_mantenimiento</strong> y se generará una incidencia técnica en la pestaña de fallas.</p>
                        </div>
                    @endif

                    @if($veredicto === 'baja')
                        <div class="p-3 bg-red-50 border border-red-200 rounded-md space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-red-900 uppercase mb-1">Tipo de Solicitud</label>
                                <select wire:model="baja_tipo" class="w-full text-sm rounded-md border-red-300">
                                    <option value="baja">Baja Definitiva</option>
                                    <option value="reemplazo">Baja con Reemplazo</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-red-900 uppercase mb-1">Sustento Técnico / Motivo de Baja</label>
                                <textarea wire:model="baja_motivo" rows="2" class="w-full text-sm rounded-md border-red-300 bg-white" placeholder="Justifique por qué la reparación es inviable técnica o económicamente..."></textarea>
                                @error('baja_motivo') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                            </div>
                            <p class="text-[11px] text-red-700">El equipo pasará a <strong>pendiente_resolucion</strong> y se enviará la propuesta al Administrador.</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Adjuntar Informe Técnico (PDF o Imagen)</label>
                        <input wire:model="informe_tecnico" type="file" class="w-full text-sm text-gray-500">
                        @error('informe_tecnico') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalEjecutar', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    @if($veredicto === 'conforme')
                        <button wire:click="registrarEjecucion" type="button" class="px-4 py-2 text-sm bg-green-700 hover:bg-green-800 text-white font-medium rounded-md shadow-sm">
                            Confirmar y Reintegrar Equipo
                        </button>
                    @elseif($veredicto === 'falla')
                        <button wire:click="registrarEjecucion" type="button" class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-md shadow-sm">
                            Registrar Falla y Dejar en Taller
                        </button>
                    @elseif($veredicto === 'baja')
                        <button wire:click="registrarEjecucion" type="button" class="px-4 py-2 text-sm bg-red-700 hover:bg-red-800 text-white font-medium rounded-md shadow-sm">
                            Derivar a Propuesta de Baja
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Modal In-Situ Solicitar Baja / Reemplazo -->
    @if($mostrarModalBaja)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Solicitar Baja o Reemplazo</h3>
                    <button wire:click="$set('mostrarModalBaja', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Equipo Seleccionado</label>
                        <input type="text" value="{{ $bajaEquipoNombre }}" readonly class="w-full text-sm bg-gray-100 rounded-md border-gray-300 text-gray-800 cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Tipo de Propuesta</label>
                        <select wire:model="bajaTipo" class="w-full text-sm rounded-md border-gray-300">
                            <option value="baja">Baja Definitiva (Obsolescencia / Desecho)</option>
                            <option value="reemplazo">Propuesta de Reemplazo</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Justificación Técnica / Económica</label>
                        <textarea wire:model="bajaMotivo" rows="4" class="w-full text-sm rounded-md border-gray-300" placeholder="Detalle técnico que sustenta el costo de reparación vs reposición (mínimo 15 caracteres)..."></textarea>
                        @error('bajaMotivo') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalBaja', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    <button wire:click="confirmarSolicitudBaja" type="button" class="px-4 py-2 text-sm bg-amber-700 hover:bg-amber-800 text-white font-medium rounded-md">Enviar Propuesta de Baja</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal In-Situ Resolución / Atención de Falla -->
    @if($mostrarModalAtender)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Resolución de Incidencia Técnica</h3>
                    <button wire:click="$set('mostrarModalAtender', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Recurso Afectado</label>
                        <input type="text" value="{{ $fallaRecursoNombre }}" readonly class="w-full text-sm bg-gray-100 rounded-md border-gray-300 text-gray-800 cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Veredicto Operativo / Estado</label>
                        <select wire:model.live="fallaNuevoEstado" class="w-full text-sm rounded-md border-gray-300 font-medium">
                            <option value="resuelta">🟢 Falla Resuelta (Reintegrar a disponible)</option>
                            <option value="en_reparacion">🟡 En Reparación Externa / Espera de Repuesto</option>
                            <option value="derivada_a_baja">🔴 Daño Irreparable (Derivar a propuesta de baja)</option>
                            <option value="en_revision">🔵 Mantener en Diagnóstico Técnico</option>
                        </select>
                    </div>

                    @if($fallaNuevoEstado === 'resuelta')
                        <div class="p-2.5 bg-emerald-50 border border-emerald-200 rounded text-xs text-emerald-800">
                            El activo será marcado como operativo y volverá a estado <strong>disponible</strong> para préstamos docentes y estudiantiles.
                        </div>
                    @elseif($fallaNuevoEstado === 'derivada_a_baja')
                        <div class="p-2.5 bg-red-50 border border-red-200 rounded text-xs text-red-800">
                            El activo pasará a <strong>pendiente_resolucion</strong> y se generará una propuesta de baja automática para revisión del Administrador.
                        </div>
                    @elseif($fallaNuevoEstado === 'en_reparacion')
                        <div class="p-2.5 bg-purple-50 border border-purple-200 rounded text-xs text-purple-800">
                            El activo permanecerá en <strong>en_mantenimiento</strong> fuera de pañol o a la espera de repuestos técnicos.
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Observaciones Técnicas / Diagnóstico</label>
                        <textarea wire:model="fallaObservaciones" rows="3" class="w-full text-sm rounded-md border-gray-300" placeholder="Detalle del diagnóstico o intervención realizada (mínimo 10 caracteres)..."></textarea>
                        @error('fallaObservaciones') <span class="text-xs text-red-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button wire:click="$set('mostrarModalAtender', false)" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancelar</button>
                    @if($fallaNuevoEstado === 'resuelta')
                        <button wire:click="confirmarAtencionFalla" type="button" class="px-4 py-2 text-sm bg-emerald-700 hover:bg-emerald-800 text-white font-medium rounded-md shadow-sm">
                            Confirmar Resolución y Reintegrar
                        </button>
                    @elseif($fallaNuevoEstado === 'derivada_a_baja')
                        <button wire:click="confirmarAtencionFalla" type="button" class="px-4 py-2 text-sm bg-red-700 hover:bg-red-800 text-white font-medium rounded-md shadow-sm">
                            Derivar a Propuesta de Baja
                        </button>
                    @elseif($fallaNuevoEstado === 'en_reparacion')
                        <button wire:click="confirmarAtencionFalla" type="button" class="px-4 py-2 text-sm bg-purple-700 hover:bg-purple-800 text-white font-medium rounded-md shadow-sm">
                            Guardar en Reparación
                        </button>
                    @else
                        <button wire:click="confirmarAtencionFalla" type="button" class="px-4 py-2 text-sm bg-slate-800 hover:bg-slate-900 text-white font-medium rounded-md shadow-sm">
                            Actualizar Diagnóstico
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
