<?php

use App\Models\Asignatura;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Services\FallaService;
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
    // Pestaña activa en panel principal
    public string $pestanaActiva = 'solicitudes'; // 'solicitudes' | 'incidencias'
    public string $filtroEstadoSolicitud = 'todos';

    // Formulario de nueva solicitud
    public bool $mostrarModalCrear = false;
    public string $fecha_requerida = '';
    public string $hora_requerida = '08:30';
    public string $asignatura_seleccionada = ''; // ID de asignatura o 'otra'
    public ?int $asignatura_id = null;
    public string $tipo_destino = 'laboratorio'; // 'sala' | 'laboratorio'
    public string $edificio_destino = 'Edificio A'; // 'Edificio A' | 'Edificio C'
    public string $sala_destino = 'A101';
    public ?int $laboratorio_destino_id = null;
    public ?int $ubicacion_destino_id = null;
    public string $observaciones = '';
    public array $itemsSeleccionados = []; // [['tipo' => 'insumo'|'equipo', 'id' => int, 'nombre' => string, 'cantidad' => float, 'es_retornable' => bool]]

    public array $salasEdificioA = [
        'A101', 'A102', 'A103', 'A104', 'A105', 'A106',
        'A201', 'A202', 'A203', 'A204', 'A205', 'A206', 'A207', 'A208',
        'A301', 'A302', 'A303', 'A304', 'A305', 'A306', 'A307', 'A308', 'A309', 'A310', 'A311', 'A312', 'A313', 'A314', 'A315', 'A316',
        'A401', 'A402', 'A403', 'A404', 'A405', 'A406', 'A407',
    ];

    public array $salasEdificioC = [
        'C101', 'C102',
        'C201', 'C202', 'C203', 'C204', 'C205', 'C206', 'C207', 'C208', 'C209', 'C210',
        'C301', 'C302', 'C303', 'C304', 'C305', 'C306', 'C307', 'C308', 'C309', 'C310', 'C311', 'C312', 'C313', 'C314',
        'C401', 'C407',
    ];

    public function seleccionarTipoDestino(string $tipo): void
    {
        $this->tipo_destino = $tipo;
        if ($tipo === 'sala') {
            $this->edificio_destino = 'Edificio A';
            $this->sala_destino = 'A101';
            $this->ubicacion_destino_id = null;
        } else {
            $primerLab = Ubicacion::where('tipo', 'laboratorio')->first() ?? Ubicacion::first();
            $this->ubicacion_destino_id = $primerLab?->id;
            $this->edificio_destino = 'Edificio A';
        }
    }

    public function updatedTipoDestino(string $valor): void
    {
        $this->seleccionarTipoDestino($valor);
    }

    public function updatedUbicacionDestinoId($val): void
    {
        if ($val) {
            $this->tipo_destino = 'laboratorio';
        }
    }

    public function updatedEdificioDestino(string $valor): void
    {
        if ($valor === 'Edificio A') {
            $this->sala_destino = 'A101';
        } else {
            $this->sala_destino = 'C101';
        }
    }

    public function updatedAsignaturaSeleccionada(string $valor): void
    {
        if ($valor === 'otra') {
            $this->asignatura_id = null;
        } else {
            $this->asignatura_id = ! empty($valor) ? (int) $valor : null;
            $this->observaciones = '';
        }
    }

    public function updatedAsignaturaId($val): void
    {
        if ($val) {
            $this->asignatura_seleccionada = (string) $val;
        }
    }

    // Modal de detalle de solicitud
    public bool $mostrarModalDetalle = false;
    public ?int $solicitudDetalleId = null;

    // Reporte de Incidencias (Equipo / Insumo en mal estado - CP-28)
    public bool $mostrarModalIncidencia = false;
    public string $tipoIncidencia = 'equipo';
    public ?int $incidenciaEquipoId = null;
    public ?int $incidenciaInsumoId = null;
    public ?int $incidenciaUbicacionId = null;
    public string $incidenciaTipoDestino = 'laboratorio';
    public string $incidenciaEdificioDestino = 'Edificio A';
    public string $incidenciaSalaDestino = 'A101';
    public string $incidenciaDescripcion = '';
    public string $incidenciaSeveridad = 'media';

    public function mount(): void
    {
        Gate::authorize('viewAny', SolicitudMaterial::class);
        $this->fecha_requerida = Carbon::tomorrow()->toDateString();
        $this->hora_requerida = '08:30';
        $primerLab = Ubicacion::where('tipo', 'laboratorio')->first() ?? Ubicacion::first();
        if ($primerLab) {
            $this->laboratorio_destino_id = $primerLab->id;
            $this->ubicacion_destino_id = $primerLab->id;
        }
        $primerAsig = $this->asignaturasDocente()->first();
        if ($primerAsig) {
            $this->asignatura_seleccionada = (string) $primerAsig->id;
            $this->asignatura_id = $primerAsig->id;
        }
    }

    public function asignaturasDocente()
    {
        return Auth::user()->asignaturas()->where('activo', true)->orderBy('nombre')->get();
    }

    public function laboratoriosDisponibles()
    {
        return Ubicacion::where('tipo', 'laboratorio')->orderBy('nombre')->get();
    }

    public function abrirModalCrear(): void
    {
        $this->resetValidation();
        if (empty($this->fecha_requerida)) {
            $this->fecha_requerida = Carbon::tomorrow()->toDateString();
        }
        $this->hora_requerida = '08:30';
        $primerAsig = $this->asignaturasDocente()->first();
        $this->asignatura_seleccionada = $primerAsig ? (string) $primerAsig->id : '';
        $this->asignatura_id = $primerAsig?->id;
        $this->tipo_destino = 'laboratorio';
        $this->edificio_destino = 'Edificio A';
        $this->sala_destino = 'A101';
        $primerLab = Ubicacion::where('tipo', 'laboratorio')->first() ?? Ubicacion::first();
        $this->laboratorio_destino_id = $primerLab?->id;
        $this->ubicacion_destino_id = $primerLab?->id;
        $this->observaciones = '';
        $this->mostrarModalCrear = true;
    }

    public function abrirModalIncidencia(): void
    {
        $this->resetValidation();
        $this->tipoIncidencia = 'equipo';
        $this->incidenciaEquipoId = Equipo::first()?->id;
        $this->incidenciaInsumoId = Insumo::first()?->id;
        $this->incidenciaTipoDestino = 'laboratorio';
        $this->incidenciaEdificioDestino = 'Edificio A';
        $this->incidenciaSalaDestino = 'A101';
        $primerLab = Ubicacion::where('tipo', 'laboratorio')->first() ?? Ubicacion::first();
        $this->incidenciaUbicacionId = $primerLab?->id;
        $this->incidenciaDescripcion = '';
        $this->incidenciaSeveridad = 'media';
        $this->mostrarModalIncidencia = true;
    }

    public function seleccionarTipoDestinoIncidencia(string $tipo): void
    {
        $this->incidenciaTipoDestino = $tipo;
        if ($tipo === 'sala') {
            $this->incidenciaEdificioDestino = 'Edificio A';
            $this->incidenciaSalaDestino = 'A101';
        } else {
            $primerLab = Ubicacion::where('tipo', 'laboratorio')->first() ?? Ubicacion::first();
            $this->incidenciaUbicacionId = $primerLab?->id;
        }
    }

    public function updatedIncidenciaEdificioDestino(string $valor): void
    {
        if ($valor === 'Edificio A') {
            $this->incidenciaSalaDestino = 'A101';
        } else {
            $this->incidenciaSalaDestino = 'C101';
        }
    }

    public function registrarIncidencia(FallaService $fallaService): void
    {
        $reglas = [
            'incidenciaDescripcion' => 'required|string|min:10',
            'incidenciaTipoDestino' => 'required|in:sala,laboratorio',
        ];
        $mensajes = [
            'incidenciaDescripcion.required' => 'Debe ingresar una descripción detallada de la anomalía técnica.',
            'incidenciaDescripcion.min' => 'La descripción debe tener al menos 10 caracteres.',
            'incidenciaTipoDestino.required' => 'Debe indicar si la incidencia se detectó en SALA o LABORATORIO.',
        ];

        if ($this->incidenciaTipoDestino === 'sala') {
            $reglas['incidenciaEdificioDestino'] = 'required|in:Edificio A,Edificio C';
            $reglas['incidenciaSalaDestino'] = 'required|string';
            $mensajes['incidenciaEdificioDestino.required'] = 'Debe seleccionar un edificio para la sala.';
            $mensajes['incidenciaSalaDestino.required'] = 'Debe seleccionar la sala de destino.';
        } else {
            $reglas['incidenciaUbicacionId'] = 'required|exists:ubicaciones,id';
            $mensajes['incidenciaUbicacionId.required'] = 'Debe indicar el laboratorio o sala donde se detectó el desperfecto.';
        }

        $this->validate($reglas, $mensajes);

        $espacioDestino = null;
        $ubicacionFinalId = null;

        if ($this->incidenciaTipoDestino === 'sala') {
            $espacioDestino = "{$this->incidenciaEdificioDestino} - {$this->incidenciaSalaDestino}";
            $ubicacionFinalId = null;
        } else {
            $lab = Ubicacion::find($this->incidenciaUbicacionId);
            $ubicacionFinalId = $lab?->id;
            $espacioDestino = $lab ? $lab->nombre : 'Laboratorio';
        }

        try {
            $falla = $fallaService->reportarIncidencia(Auth::user(), [
                'tipo_item' => $this->tipoIncidencia,
                'equipo_id' => $this->tipoIncidencia === 'equipo' ? $this->incidenciaEquipoId : null,
                'insumo_id' => $this->tipoIncidencia === 'insumo' ? $this->incidenciaInsumoId : null,
                'ubicacion_id' => $ubicacionFinalId,
                'tipo_destino' => $this->incidenciaTipoDestino,
                'edificio_destino' => $this->incidenciaTipoDestino === 'sala' ? $this->incidenciaEdificioDestino : null,
                'espacio_destino' => $espacioDestino,
                'descripcion' => $this->incidenciaDescripcion,
                'severidad' => $this->incidenciaSeveridad,
            ]);

            $this->mostrarModalIncidencia = false;
            $this->pestanaActiva = 'incidencias';
            session()->flash('mensaje', "¡Incidencia #{$falla->id} reportada exitosamente! Se ha generado el reporte formal en PDF y se notificó por correo a Pañol y Administración.");
            session()->flash('pdf_url', asset("storage/incidencias/Reporte_Incidencia_{$falla->id}.pdf"));
        } catch (\Exception $e) {
            $this->addError('incidencia', $e->getMessage());
        }
    }

    public function vaciarItems(): void
    {
        $this->itemsSeleccionados = [];
        session()->flash('info_item', 'Se han vaciado todos los ítems de la solicitud.');
    }

    public function estaSeleccionado(string $tipo, int $id): bool
    {
        foreach ($this->itemsSeleccionados as $item) {
            if ($item['tipo'] === $tipo && $item['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    public function toggleItem(string $tipo, int $id): void
    {
        foreach ($this->itemsSeleccionados as $index => $item) {
            if ($item['tipo'] === $tipo && $item['id'] === $id) {
                $this->removerItem($index);
                session()->flash('info_item', 'Ítem removido de la solicitud.');
                return;
            }
        }

        $this->agregarItem($tipo, $id);
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
            session()->flash('info_item', 'Equipo "' . $equipo->nombre . '" agregado a la solicitud.');
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
            session()->flash('info_item', 'Insumo "' . $insumo->nombre . '" agregado a la solicitud.');
        }
    }

    public function removerItem(int $index): void
    {
        unset($this->itemsSeleccionados[$index]);
        $this->itemsSeleccionados = array_values($this->itemsSeleccionados);
        session()->flash('info_item', 'Ítem eliminado de la solicitud.');
    }

    public function registrarSolicitud(PrestamoService $prestamoService): void
    {
        Gate::authorize('create', SolicitudMaterial::class);

        $reglas = [
            'fecha_requerida' => 'required|date|after_or_equal:today',
            'hora_requerida' => 'required|string',
            'tipo_destino' => 'required|in:sala,laboratorio',
            'itemsSeleccionados' => 'required|array|min:1',
            'itemsSeleccionados.*.cantidad' => 'required|numeric|min:1',
        ];

        $mensajes = [
            'fecha_requerida.required' => 'La fecha requerida es obligatoria.',
            'fecha_requerida.after_or_equal' => 'La fecha requerida no puede ser anterior a hoy.',
            'hora_requerida.required' => 'La hora requerida es obligatoria para la preparación en pañol.',
            'tipo_destino.required' => 'Debe indicar si el destino es SALA o LABORATORIO.',
            'itemsSeleccionados.min' => 'Debe agregar al menos un ítem a la solicitud.',
            'itemsSeleccionados.*.cantidad.min' => 'La cantidad mínima por ítem es 1.',
        ];

        if ($this->tipo_destino === 'sala') {
            $reglas['edificio_destino'] = 'required|in:Edificio A,Edificio C';
            $reglas['sala_destino'] = 'required|string';
            $mensajes['edificio_destino.required'] = 'Debe seleccionar un edificio para la sala.';
            $mensajes['sala_destino.required'] = 'Debe seleccionar la sala de destino.';
        } else {
            $reglas['ubicacion_destino_id'] = 'required|exists:ubicaciones,id';
            $mensajes['ubicacion_destino_id.required'] = 'Debe seleccionar el laboratorio de destino.';
        }

        if ($this->asignatura_seleccionada === 'otra') {
            $reglas['observaciones'] = 'required|string|min:3';
            $mensajes['observaciones.required'] = 'Debe especificar el nombre o detalle de la otra actividad.';
            $mensajes['observaciones.min'] = 'La descripción de la actividad debe tener al menos 3 caracteres.';
        }

        $this->validate($reglas, $mensajes);

        $itemsFormateados = [];
        foreach ($this->itemsSeleccionados as $item) {
            $itemsFormateados[] = [
                'insumo_id' => $item['tipo'] === 'insumo' ? $item['id'] : null,
                'equipo_id' => $item['tipo'] === 'equipo' ? $item['id'] : null,
                'cantidad' => (float) $item['cantidad'],
            ];
        }

        $asignaturaIdFinal = null;
        if ($this->asignatura_seleccionada === 'otra') {
            $asignaturaIdFinal = null;
        } elseif (! empty($this->asignatura_id)) {
            $asignaturaIdFinal = $this->asignatura_id;
        } elseif (! empty($this->asignatura_seleccionada)) {
            $asignaturaIdFinal = (int) $this->asignatura_seleccionada;
        }

        $ubicacionDestinoId = null;
        $espacioDestino = '';

        if ($this->tipo_destino === 'sala') {
            $espacioDestino = "{$this->edificio_destino} - {$this->sala_destino}";
        } else {
            $lab = Ubicacion::find($this->ubicacion_destino_id ?: $this->laboratorio_destino_id);
            $ubicacionDestinoId = $lab?->id;
            $espacioDestino = $lab ? $lab->nombre : 'Laboratorio';
        }

        try {
            $prestamoService->crearSolicitud(
                Auth::user(),
                [
                    'fecha_requerida' => $this->fecha_requerida,
                    'hora_requerida' => $this->hora_requerida,
                    'asignatura_id' => $asignaturaIdFinal,
                    'ubicacion_destino_id' => $ubicacionDestinoId,
                    'tipo_destino' => $this->tipo_destino,
                    'edificio_destino' => $this->tipo_destino === 'sala' ? $this->edificio_destino : 'Edificio A',
                    'espacio_destino' => $espacioDestino,
                    'observaciones' => $this->observaciones,
                ],
                $itemsFormateados
            );

            session()->flash('mensaje', '¡Solicitud registrada exitosamente! El pañolero despachará los materiales al destino indicado.');
            $this->mostrarModalCrear = false;
            $this->itemsSeleccionados = [];
            $this->observaciones = '';
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelarSolicitud(int $solicitudId, PrestamoService $prestamoService): void
    {
        $solicitud = SolicitudMaterial::findOrFail($solicitudId);
        Gate::authorize('update', $solicitud);

        try {
            $prestamoService->cancelarSolicitud($solicitud, Auth::user());
            session()->flash('mensaje', '¡Solicitud #SOL-' . str_pad($solicitud->id, 5, '0', STR_PAD_LEFT) . ' cancelada exitosamente!');
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

    public function misIncidencias()
    {
        return \App\Models\Falla::with(['equipo', 'insumo', 'ubicacion', 'documentos'])
            ->where('reportada_por', Auth::id())
            ->latest('fecha_reporte')
            ->get();
    }

    public function disponibilidad(PrestamoService $prestamoService)
    {
        return $prestamoService->consultarDisponibilidad($this->buscarDisponibilidad);
    }
}; ?>

<div class="py-6 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <!-- Encabezado adaptable a dispositivos móviles (CP-19, PI-07, CP-28) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-7 h-7 text-red-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <span>Solicitud de Materiales</span>
                <span class="sr-only">Portal Docente: Solicitud de Materiales</span>
            </h1>
        </div>
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <button
                wire:click="abrirModalCrear"
                class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 min-h-[44px] bg-red-700 hover:bg-red-800 text-white font-semibold text-sm rounded-lg shadow-sm hover:shadow transition active:scale-95 relative"
            >
                <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Nueva Solicitud</span>
                @if(count($itemsSeleccionados) > 0)
                    <span class="ml-2 px-2 py-0.5 text-xs font-bold bg-white text-red-700 rounded-full shadow-xs">
                        {{ count($itemsSeleccionados) }}
                    </span>
                @endif
            </button>
        </div>
    </div>

    <!-- Alertas Flash -->
    @if(session()->has('mensaje'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-sm font-medium">{{ session('mensaje') }}</span>
            </div>
            @if(session()->has('pdf_url'))
                <a href="{{ session('pdf_url') }}" target="_blank" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-xs transition shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Ver / Descargar PDF del Reporte
                </a>
            @endif
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

    @if(session()->has('info_item') && ! $mostrarModalCrear)
        <div class="mb-4 p-3.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 flex items-center justify-between gap-2 animate-in fade-in duration-150">
            <div class="flex items-center gap-2 min-w-0">
                <svg class="w-5 h-5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-xs sm:text-sm font-medium truncate">{{ session('info_item') }}</span>
            </div>
            @if(count($itemsSeleccionados) > 0)
                <button
                    wire:click="abrirModalCrear"
                    class="text-xs font-semibold text-blue-700 hover:text-blue-900 underline shrink-0"
                >
                    Ver Solicitud ({{ count($itemsSeleccionados) }}) &rarr;
                </button>
            @endif
        </div>
    @endif

    @if(session()->has('error_item') && ! $mostrarModalCrear)
        <div class="mb-4 p-3.5 rounded-lg bg-red-50 border border-red-200 text-red-800 flex items-center gap-2 animate-in fade-in duration-150">
            <svg class="w-5 h-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <span class="text-xs sm:text-sm font-medium">{{ session('error_item') }}</span>
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
                                        @php $enSolicitud = $this->estaSeleccionado('equipo', $eq->id); @endphp
                                        @if($enSolicitud)
                                            <button
                                                wire:click="toggleItem('equipo', {{ $eq->id }})"
                                                class="shrink-0 px-2 py-1 bg-emerald-50 hover:bg-red-50 text-emerald-700 hover:text-red-700 border border-emerald-300 hover:border-red-300 rounded text-[11px] font-semibold transition group flex items-center gap-1"
                                                title="Haga clic para quitar de la solicitud"
                                            >
                                                <span class="group-hover:hidden">✓ En Solicitud</span>
                                                <span class="hidden group-hover:inline">✕ Quitar</span>
                                            </button>
                                        @else
                                            <button
                                                wire:click="toggleItem('equipo', {{ $eq->id }})"
                                                class="shrink-0 px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded text-[11px] font-medium transition"
                                                title="Agregar a solicitud"
                                            >
                                                + Pedir
                                            </button>
                                        @endif
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
                                            @php $enSolicitud = $this->estaSeleccionado('insumo', $ins->id); @endphp
                                            @if($enSolicitud)
                                                <button
                                                    wire:click="toggleItem('insumo', {{ $ins->id }})"
                                                    class="shrink-0 px-2 py-1 bg-emerald-50 hover:bg-red-50 text-emerald-700 hover:text-red-700 border border-emerald-300 hover:border-red-300 rounded text-[11px] font-semibold transition group flex items-center gap-1"
                                                    title="Haga clic para quitar de la solicitud"
                                                >
                                                    <span class="group-hover:hidden">✓ En Solicitud</span>
                                                    <span class="hidden group-hover:inline">✕ Quitar</span>
                                                </button>
                                            @else
                                                <button
                                                    wire:click="toggleItem('insumo', {{ $ins->id }})"
                                                    class="shrink-0 px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded text-[11px] font-medium transition"
                                                    title="Agregar a solicitud"
                                                >
                                                    + Pedir
                                                </button>
                                            @endif
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
            @php 
                $listaSolicitudes = $this->solicitudes(); 
            @endphp

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 border-b border-gray-100 pb-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Mis Solicitudes</h2>
                </div>
                <!-- Filtro por Estado -->
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-500 font-medium">Filtrar:</label>
                    <select
                        wire:model.live="filtroEstadoSolicitud"
                        class="text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 py-1.5"
                    >
                        <option value="todos">Todas</option>
                        <option value="pendiente">Solicitadas</option>
                        <option value="aprobada">Aprobadas</option>
                        <option value="entregada_abierta">Préstamo Activo</option>
                        <option value="cerrada">Cerradas</option>
                        <option value="cancelada">Canceladas</option>
                        <option value="rechazada">Rechazadas</option>
                        <option value="sin_disponibilidad">Sin Disponibilidad</option>
                    </select>
                </div>
            </div>

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
                                    <span class="font-bold text-slate-900 text-sm">#SOL-{{ str_pad($sol->id, 5, '0', STR_PAD_LEFT) }}</span>
                                    <span class="text-xs text-slate-500">&bull; Requerida: <strong class="text-slate-700">{{ $sol->fecha_requerida->format('d/m/Y') }}</strong></span>
                                </div>
                                <div>
                                    @php
                                        $labelEstado = match($sol->estado) {
                                            'pendiente' => 'Solicitado',
                                            'aprobada' => 'Aprobada (Lista para Retiro)',
                                            'entregada_abierta' => 'Préstamo Abierto',
                                            'cerrada' => 'Cerrada / Finalizada',
                                            'cancelada' => 'Cancelada',
                                            'rechazada' => 'Rechazada',
                                            'sin_disponibilidad' => 'Sin Disponibilidad',
                                            default => ucfirst(str_replace('_', ' ', $sol->estado)),
                                        };
                                    @endphp
                                    <x-status-badge :estado="$sol->estado" :label="$labelEstado" />
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

                                <!-- Destino, Horario y Asignatura / Actividad -->
                                <div class="mt-3 p-2.5 rounded-lg bg-gray-50/80 border border-gray-100 text-xs space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-gray-700">
                                        <span class="inline-flex items-center gap-1 font-medium">
                                            <svg class="w-3.5 h-3.5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            Destino: <strong class="text-gray-900">{{ $sol->espacio_destino ?: ($sol->ubicacionDestino->nombre ?? 'Pañol Central') }}</strong>
                                        </span>
                                        @if($sol->hora_requerida)
                                            <span class="inline-flex items-center gap-1 text-gray-600">
                                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Hora: <strong class="text-gray-800">{{ substr($sol->hora_requerida, 0, 5) }} hrs</strong>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 text-gray-700">
                                        <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                        @if($sol->asignatura)
                                            <span>Asignatura: <strong class="text-gray-900">{{ $sol->asignatura->codigo }} - {{ $sol->asignatura->nombre }}</strong></span>
                                        @elseif($sol->observaciones)
                                            <span>Actividad: <strong class="text-gray-900">{{ $sol->observaciones }}</strong></span>
                                        @else
                                            <span class="text-gray-500 italic">Actividad Académica General</span>
                                        @endif
                                    </div>
                                    @if($sol->motivo_cancelacion)
                                        <div class="text-[11px] text-red-600 font-medium">
                                            Motivo cancelación: {{ $sol->motivo_cancelacion }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Botón Cancelar y Detalle -->
                            <div class="mt-3 pt-2 border-t border-gray-100 flex items-center justify-between">
                                <div>
                                    @if($sol->estado === 'pendiente')
                                        <button
                                            type="button"
                                            wire:click="cancelarSolicitud({{ $sol->id }})"
                                            wire:confirm="¿Está seguro de que desea cancelar esta solicitud?"
                                            class="text-xs text-gray-500 hover:text-red-600 font-semibold flex items-center gap-1 transition"
                                            title="Cancelar esta solicitud si no asistirá a clase"
                                        >
                                            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Cancelar solicitud
                                        </button>
                                    @endif
                                </div>
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

                    <!-- Parámetros de Fecha y Hora -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Fecha Requerida -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Fecha Requerida <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                wire:model="fecha_requerida"
                                min="{{ date('Y-m-d') }}"
                                class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                            />
                            @error('fecha_requerida')
                                <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Hora Requerida -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Hora Requerida <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="time"
                                wire:model="hora_requerida"
                                class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                            />
                            @error('hora_requerida')
                                <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Asignatura / Taller Práctico -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Asignatura / Taller Práctico <span class="text-red-500">*</span>
                        </label>
                        <select
                            wire:model.live="asignatura_seleccionada"
                            class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                        >
                            <option value="">Seleccione Asignatura...</option>
                            @foreach($this->asignaturasDocente() as $asig)
                                <option value="{{ $asig->id }}">{{ $asig->codigo }} - {{ $asig->nombre }}</option>
                            @endforeach
                            <option value="otra">Otra actividad</option>
                        </select>
                        @error('asignatura_seleccionada')
                            <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Selector Multinivel de Destino (SALA vs LABORATORIO) -->
                    <div class="p-3 bg-gray-50/90 rounded-xl border border-gray-200 space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                                Sala / Laboratorio de Destino <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    wire:click="seleccionarTipoDestino('sala')"
                                    class="py-2 px-3 text-xs font-semibold rounded-lg border text-center transition {{ $tipo_destino === 'sala' ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' }}"
                                >
                                    SALA
                                </button>
                                <button
                                    type="button"
                                    wire:click="seleccionarTipoDestino('laboratorio')"
                                    class="py-2 px-3 text-xs font-semibold rounded-lg border text-center transition {{ $tipo_destino === 'laboratorio' ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' }}"
                                >
                                    LABORATORIO
                                </button>
                            </div>
                        </div>

                        @if($tipo_destino === 'sala')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-gray-200/70">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                        Edificio <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        wire:model.live="edificio_destino"
                                        class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                    >
                                        <option value="Edificio A">Edificio A</option>
                                        <option value="Edificio C">Edificio C</option>
                                    </select>
                                    @error('edificio_destino')
                                        <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                        Sala ({{ $edificio_destino }}) <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        wire:model="sala_destino"
                                        class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                    >
                                        @if($edificio_destino === 'Edificio A')
                                            @foreach($salasEdificioA as $sala)
                                                <option value="{{ $sala }}">{{ $sala }}</option>
                                            @endforeach
                                        @else
                                            @foreach($salasEdificioC as $sala)
                                                <option value="{{ $sala }}">{{ $sala }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('sala_destino')
                                        <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @else
                            <div class="pt-1 border-t border-gray-200/70">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-[11px] font-semibold text-gray-600">
                                        Laboratorio <span class="text-red-500">*</span>
                                    </label>
                                    <span class="text-[10px] text-gray-500 italic">(Sede INACAP Temuco)</span>
                                </div>
                                <select
                                    wire:model="ubicacion_destino_id"
                                    class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                >
                                    @foreach($this->laboratoriosDisponibles() as $lab)
                                        <option value="{{ $lab->id }}">{{ $lab->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('ubicacion_destino_id')
                                    <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <!-- Ítems Agregados -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-xs font-semibold text-gray-700">
                                Ítems a Solicitar <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-gray-500 font-medium">Total ítems: {{ count($itemsSeleccionados) }}</span>
                                @if(count($itemsSeleccionados) > 0)
                                    <button
                                        type="button"
                                        wire:click="vaciarItems"
                                        class="text-[11px] text-red-600 hover:text-red-800 underline font-medium"
                                    >
                                        Vaciar lista
                                    </button>
                                @endif
                            </div>
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

                    <!-- Observaciones condicionales (Solo cuando se elige "Otra actividad") -->
                    @if($asignatura_seleccionada === 'otra')
                        <div class="p-3 bg-amber-50/60 border border-amber-200 rounded-xl space-y-1">
                            <label class="block text-xs font-semibold text-amber-900 mb-1">
                                Especifique qué actividad o taller <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                wire:model="observaciones"
                                rows="2"
                                placeholder="Ej. Taller extracurricular de Robótica, Refuerzo de programación..."
                                class="w-full text-xs border-amber-300 rounded-lg focus:ring-red-500 focus:border-red-500 bg-white"
                            ></textarea>
                            @error('observaciones')
                                <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
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
                        <span wire:loading.remove wire:target="registrarSolicitud">Enviar</span>
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg">
                            <div>
                                <span class="text-gray-500 font-medium">Estado:</span>
                                <span class="font-bold text-gray-800 ml-1">{{ $solDetalle->estado === 'pendiente' ? 'SOLICITADO' : strtoupper($solDetalle->estado) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 font-medium">Destino:</span>
                                <span class="font-bold text-gray-800 ml-1">{{ $solDetalle->espacio_destino ?: ($solDetalle->ubicacionDestino->nombre ?? 'Pañol') }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 font-medium">Asignatura / Actividad:</span>
                                <span class="font-bold text-gray-800 ml-1">{{ $solDetalle->asignatura ? ($solDetalle->asignatura->codigo . ' - ' . $solDetalle->asignatura->nombre) : ($solDetalle->observaciones ?: 'Actividad Académica General') }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 font-medium">Revisada Por:</span>
                                <span class="text-gray-800 ml-1">{{ $solDetalle->revisor->name ?? 'En espera de despacho' }}</span>
                            </div>
                            @if($solDetalle->motivo_cancelacion)
                                <div class="col-span-1 sm:col-span-2 text-red-600 font-semibold">
                                    Motivo cancelación: {{ $solDetalle->motivo_cancelacion }}
                                </div>
                            @endif
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

    <!-- Barra flotante inferior para selección rápida (Móvil y Escritorio - PI-07 / CP-19) -->
    @if(count($itemsSeleccionados) > 0 && ! $mostrarModalCrear && ! $mostrarModalDetalle)
        <div class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:max-w-md z-40 bg-gray-900 text-white rounded-xl shadow-2xl p-3.5 border border-gray-700 flex items-center justify-between gap-3 animate-in fade-in slide-in-from-bottom-4 duration-200">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white">
                    {{ count($itemsSeleccionados) }}
                </span>
                <div class="truncate">
                    <div class="text-xs font-semibold text-white truncate">
                        {{ count($itemsSeleccionados) === 1 ? '1 material en canasta' : count($itemsSeleccionados) . ' materiales en canasta' }}
                    </div>
                    <div class="text-[10px] text-gray-400">Listo para formalizar solicitud</div>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    wire:click="vaciarItems"
                    class="text-xs text-gray-300 hover:text-white px-2 py-1 transition"
                    title="Vaciar todos los ítems seleccionados"
                >
                    Vaciar
                </button>
                <button
                    type="button"
                    wire:click="abrirModalCrear"
                    class="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white font-medium text-xs rounded-lg shadow transition active:scale-95"
                >
                    Ver y Enviar &rarr;
                </button>
            </div>
        </div>
    @endif

    <!-- MODAL: Reporte de Incidencias con PDF y Envío Asíncrono (CP-28, Sección 3.6) -->
    @if($mostrarModalIncidencia)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150 border border-slate-200">
                <div class="px-5 py-4 bg-amber-600 text-white flex items-center justify-between">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Reportar Incidencia (Equipo / Insumo en Mal Estado)
                    </h3>
                    <button wire:click="$set('mostrarModalIncidencia', false)" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto text-xs sm:text-sm">
                    <p class="text-xs text-slate-500">
                        Al enviar este reporte, el sistema generará automáticamente un comprobante PDF institucional respaldado en el expediente digital y notificará inmediatamente a Pañoleros y Administradores.
                    </p>

                    @error('incidencia')
                        <div class="p-3 rounded-lg bg-red-50 text-red-700 text-xs font-semibold">
                            {{ $message }}
                        </div>
                    @enderror

                    <!-- Tipo de Recurso: Equipo o Insumo -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Tipo de Recurso Afectado *</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer {{ $tipoIncidencia === 'equipo' ? 'border-amber-500 bg-amber-50 text-amber-900 font-bold' : 'border-slate-200 text-slate-700' }}">
                                <input type="radio" wire:model.live="tipoIncidencia" value="equipo" class="text-amber-600 focus:ring-amber-500">
                                <span>Equipo / Maquinaria</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer {{ $tipoIncidencia === 'insumo' ? 'border-amber-500 bg-amber-50 text-amber-900 font-bold' : 'border-slate-200 text-slate-700' }}">
                                <input type="radio" wire:model.live="tipoIncidencia" value="insumo" class="text-amber-600 focus:ring-amber-500">
                                <span>Insumo / Material</span>
                            </label>
                        </div>
                    </div>

                    <!-- Selector específico según tipo -->
                    @if($tipoIncidencia === 'equipo')
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Seleccione el Equipo Afectado *</label>
                            <select wire:model="incidenciaEquipoId" class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                @foreach(\App\Models\Equipo::orderBy('nombre')->get() as $eq)
                                    <option value="{{ $eq->id }}">{{ $eq->nombre }} (Marca: {{ $eq->marca }} | S/N: {{ $eq->numero_serie }})</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Seleccione el Insumo Afectado *</label>
                            <select wire:model="incidenciaInsumoId" class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                @foreach(\App\Models\Insumo::orderBy('nombre')->get() as $ins)
                                    <option value="{{ $ins->id }}">{{ $ins->nombre }} (Código: {{ $ins->codigo_barras }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <!-- Selector Multinivel de Destino / Dónde se detectó la novedad (SALA vs LABORATORIO) -->
                    <div class="p-3 bg-gray-50/90 rounded-xl border border-gray-200 space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                                Sala / Laboratorio de Destino <span class="text-amber-600">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    wire:click="seleccionarTipoDestinoIncidencia('sala')"
                                    class="py-2 px-3 text-xs font-semibold rounded-lg border text-center transition {{ $incidenciaTipoDestino === 'sala' ? 'bg-amber-600 text-white border-amber-600 shadow-xs hover:bg-amber-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' }}"
                                >
                                    SALA
                                </button>
                                <button
                                    type="button"
                                    wire:click="seleccionarTipoDestinoIncidencia('laboratorio')"
                                    class="py-2 px-3 text-xs font-semibold rounded-lg border text-center transition {{ $incidenciaTipoDestino === 'laboratorio' ? 'bg-amber-600 text-white border-amber-600 shadow-xs hover:bg-amber-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' }}"
                                >
                                    LABORATORIO
                                </button>
                            </div>
                            @error('incidenciaTipoDestino')
                                <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>

                        @if($incidenciaTipoDestino === 'sala')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-gray-200/70">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                        Edificio <span class="text-amber-600">*</span>
                                    </label>
                                    <select
                                        wire:model.live="incidenciaEdificioDestino"
                                        class="w-full text-xs border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500"
                                    >
                                        <option value="Edificio A">Edificio A</option>
                                        <option value="Edificio C">Edificio C</option>
                                    </select>
                                    @error('incidenciaEdificioDestino')
                                        <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                        Sala ({{ $incidenciaEdificioDestino }}) <span class="text-amber-600">*</span>
                                    </label>
                                    <select
                                        wire:model="incidenciaSalaDestino"
                                        class="w-full text-xs border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500"
                                    >
                                        @if($incidenciaEdificioDestino === 'Edificio A')
                                            @foreach($salasEdificioA as $sala)
                                                <option value="{{ $sala }}">{{ $sala }}</option>
                                            @endforeach
                                        @else
                                            @foreach($salasEdificioC as $sala)
                                                <option value="{{ $sala }}">{{ $sala }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('incidenciaSalaDestino')
                                        <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @else
                            <div class="pt-1 border-t border-gray-200/70">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-[11px] font-semibold text-gray-600">
                                        Laboratorio / Taller <span class="text-amber-600">*</span>
                                    </label>
                                    <span class="text-[10px] text-gray-500 italic">(Sede INACAP Temuco)</span>
                                </div>
                                <select
                                    wire:model="incidenciaUbicacionId"
                                    class="w-full text-xs border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500"
                                >
                                    @foreach($this->laboratoriosDisponibles() as $lab)
                                        <option value="{{ $lab->id }}">{{ $lab->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('incidenciaUbicacionId')
                                    <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <!-- Severidad -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nivel de Severidad *</label>
                        <select wire:model="incidenciaSeveridad" class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                            <option value="baja">Baja (Anomalía estética o menor)</option>
                            <option value="media">Media (Falla parcial / funcional)</option>
                            <option value="alta">Alta (Equipo inoperativo / riesgo eléctrico)</option>
                        </select>
                    </div>

                    <!-- Descripción del desperfecto -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Descripción del Desperfecto o Avería *</label>
                        <textarea wire:model="incidenciaDescripcion" rows="4" placeholder="Detalle los síntomas, ruidos extraños, componentes rotos o fallas detectadas..." class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500"></textarea>
                        @error('incidenciaDescripcion') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button wire:click="$set('mostrarModalIncidencia', false)" type="button" class="px-4 py-2 text-xs sm:text-sm font-semibold text-slate-600 hover:bg-slate-200 rounded-lg">
                        Cancelar
                    </button>
                    <button wire:click="registrarIncidencia" type="button" class="px-5 py-2 text-xs sm:text-sm font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm">
                        Emitir Reporte y Generar PDF
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
