<?php

use App\Exceptions\EquipoNoDisponibleException;
use App\Exceptions\OperacionDuplicadaException;
use App\Exceptions\StockInsuficienteException;
use App\Exceptions\StockReservadoClasesException;
use App\Models\DetalleSolicitud;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\ParametroSistema;
use App\Models\SolicitudMaterial;
use App\Services\PrestamoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $pestanaActiva = 'abiertas'; // abiertas, vencidas, historial (compatible con legacy: solicitudes, activos)
    public string $filtroEstado = 'todas'; // todas, pendientes, aprobadas, entregadas_abiertas, cerradas
    public string $busquedaRapida = '';
    public string $escaneoCodigoBarras = '';
    public ?string $resultadoEscaneo = null;

    // Modal de Entrega / Despacho
    public bool $mostrarModalEntrega = false;
    public ?int $solicitudEntregaId = null;
    public string $receptorNombre = '';
    public string $receptorTipo = 'docente'; // docente o estudiante
    public string $receptorCredencialRut = '';
    public bool $credencialVerificada = false;
    public string $horaEntrega = '';
    public string $horaDevolucionAcordada = '';
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

    // Modal Préstamo Presencial en Mesón a Estudiantes
    public bool $mostrarModalPresencial = false;
    public ?string $errorModalPresencial = null;
    public string $presencialNombre = '';
    public string $presencialRut = '';
    public bool $presencialCredencialVerificada = false;
    public string $presencialHoraDevolucion = '';
    public string $presencialTipoDestino = 'laboratorio'; // 'sala' | 'laboratorio'
    public string $presencialEdificioDestino = 'Edificio A'; // 'Edificio A' | 'Edificio C'
    public string $presencialSalaDestino = 'A101';
    public ?int $presencialUbicacionId = null;
    public ?int $presencialAsignaturaId = null;
    public string $presencialObservaciones = '';
    public array $presencialItems = [];
    public string $presencialBusqueda = '';

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

    public function updatedPresencialRut(string $val): void
    {
        $this->presencialRut = \App\Services\FormatoHelper::formatearRut($val) ?? $val;
    }

    public function updatedReceptorCredencialRut(string $val): void
    {
        $this->receptorCredencialRut = \App\Services\FormatoHelper::formatearRut($val) ?? $val;
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', Entrega::class);
    }

    public function conteoAbiertas(): int
    {
        return SolicitudMaterial::whereIn('estado', ['pendiente', 'aprobada', 'entregada_abierta'])->count();
    }

    public function conteoVencidas(): int
    {
        $hoy = now()->toDateString();
        $hora = now()->format('H:i:s');

        return Entrega::where('receptor_tipo', 'estudiante')
            ->whereHas('solicitud', fn ($q) => $q->where('estado', 'entregada_abierta'))
            ->where(function ($q) use ($hoy, $hora) {
                $q->whereDate('fecha_entrega', '<', $hoy)
                  ->orWhere(function ($sub) use ($hoy, $hora) {
                      $sub->whereDate('fecha_entrega', '=', $hoy)
                          ->whereNotNull('hora_devolucion_acordada')
                          ->where('hora_devolucion_acordada', '<', $hora);
                  });
            })
            ->count();
    }

    public function solicitudesAbiertas()
    {
        $query = SolicitudMaterial::with([
            'solicitante',
            'revisor',
            'detalles.insumo',
            'detalles.equipo',
            'entregas.devoluciones',
            'asignatura',
            'ubicacionDestino',
        ])
        ->whereIn('estado', ['pendiente', 'aprobada', 'entregada_abierta'])
        ->orderByDesc('id');

        if ($this->filtroEstado === 'pendientes') {
            $query->where('estado', 'pendiente');
        } elseif ($this->filtroEstado === 'aprobadas') {
            $query->where('estado', 'aprobada');
        } elseif ($this->filtroEstado === 'entregadas_abiertas') {
            $query->where('estado', 'entregada_abierta');
        }

        if (trim($this->busquedaRapida) !== '') {
            $term = mb_strtolower(trim($this->busquedaRapida));
            $t = '%' . $term . '%';
            $query->where(function ($q) use ($t, $term) {
                if (is_numeric($term)) {
                    $q->where('id', (int) $term);
                }
                $q->orWhereRaw('LOWER(observaciones) LIKE ?', [$t])
                  ->orWhereHas('solicitante', fn ($u) => $u->whereRaw('LOWER(nombre) LIKE ?', [$t])->orWhereRaw('LOWER(email) LIKE ?', [$t]))
                  ->orWhereHas('entregas', fn ($e) => $e->whereRaw('LOWER(receptor_nombre) LIKE ?', [$t])->orWhereRaw('LOWER(receptor_credencial_rut) LIKE ?', [$t]))
                  ->orWhereHas('detalles.equipo', fn ($eq) => $eq->whereRaw('LOWER(nombre) LIKE ?', [$t])->orWhereRaw('LOWER(codigo_barras) LIKE ?', [$t]))
                  ->orWhereHas('detalles.insumo', fn ($ins) => $ins->whereRaw('LOWER(nombre) LIKE ?', [$t])->orWhereRaw('LOWER(codigo_barras) LIKE ?', [$t]));
            });
        }

        return $query->paginate(15);
    }

    public function prestamosVencidos()
    {
        $hoy = now()->toDateString();
        $hora = now()->format('H:i:s');

        $query = Entrega::with([
            'solicitud.solicitante',
            'solicitud.detalles.insumo',
            'solicitud.detalles.equipo',
            'entregador',
            'devoluciones',
        ])
        ->where('receptor_tipo', 'estudiante')
        ->whereHas('solicitud', fn ($q) => $q->where('estado', 'entregada_abierta'))
        ->where(function ($q) use ($hoy, $hora) {
            $q->whereDate('fecha_entrega', '<', $hoy)
              ->orWhere(function ($sub) use ($hoy, $hora) {
                  $sub->whereDate('fecha_entrega', '=', $hoy)
                      ->whereNotNull('hora_devolucion_acordada')
                      ->where('hora_devolucion_acordada', '<', $hora);
              });
        })
        ->orderBy('hora_devolucion_acordada');

        if (trim($this->busquedaRapida) !== '') {
            $term = mb_strtolower(trim($this->busquedaRapida));
            $t = '%' . $term . '%';
            $query->where(function ($q) use ($t, $term) {
                if (is_numeric($term)) {
                    $q->where('id', (int) $term);
                }
                $q->orWhereRaw('LOWER(receptor_nombre) LIKE ?', [$t])
                  ->orWhereRaw('LOWER(receptor_credencial_rut) LIKE ?', [$t])
                  ->orWhereHas('solicitud.detalles.equipo', fn ($eq) => $eq->whereRaw('LOWER(nombre) LIKE ?', [$t])->orWhereRaw('LOWER(codigo_barras) LIKE ?', [$t]))
                  ->orWhereHas('solicitud.detalles.insumo', fn ($ins) => $ins->whereRaw('LOWER(nombre) LIKE ?', [$t])->orWhereRaw('LOWER(codigo_barras) LIKE ?', [$t]));
            });
        }

        return $query->paginate(15);
    }

    public function historialEntregas()
    {
        $query = Entrega::with([
            'solicitud.solicitante',
            'solicitud.detalles.insumo',
            'solicitud.detalles.equipo',
            'entregador',
            'devoluciones.recibidor',
        ])
        ->orderByDesc('id');

        if (trim($this->busquedaRapida) !== '') {
            $term = mb_strtolower(trim($this->busquedaRapida));
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

    // Alias retrocompatibles para tests existentes
    public function solicitudes()
    {
        return $this->solicitudesAbiertas();
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

    public function escanearCodigo(): void
    {
        $codigo = trim($this->escaneoCodigoBarras);
        if ($codigo === '') {
            return;
        }

        $equipo = Equipo::with('ubicacion')->where('codigo_barras', $codigo)->first();
        if ($equipo) {
            $this->resultadoEscaneo = "Equipo encontrado: {$equipo->nombre} (Estado: {$equipo->estado}) en {$equipo->ubicacion->nombre}.";
            $this->escaneoCodigoBarras = '';
            return;
        }

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
        $this->receptorCredencialRut = '';
        $this->credencialVerificada = false;
        $this->horaEntrega = now()->format('H:i');
        $this->horaDevolucionAcordada = now()->addHours(3)->format('H:i');

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
        Gate::authorize('create', Entrega::class);

        $rules = [
            'receptorNombre' => 'required|string|min:3',
            'receptorTipo' => 'required|in:docente,estudiante',
            'detallesSeleccionadosIds' => 'required|array|min:1',
        ];

        if ($this->receptorTipo === 'estudiante') {
            $rules['receptorCredencialRut'] = 'required|string|min:4';
            $rules['credencialVerificada'] = 'accepted';
            $rules['horaDevolucionAcordada'] = 'required|string';
        }

        $this->validate($rules, [
            'receptorNombre.required' => 'El nombre del receptor es obligatorio.',
            'receptorCredencialRut.required' => 'El RUT o credencial institucional del estudiante es obligatorio.',
            'credencialVerificada.accepted' => 'Debe confirmar haber verificado y retenido la credencial física del estudiante.',
            'horaDevolucionAcordada.required' => 'Debe ingresar la hora acordada para la devolución.',
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
                $this->detallesSeleccionadosIds,
                \App\Services\FormatoHelper::formatearRut($this->receptorCredencialRut) ?? $this->receptorCredencialRut,
                $this->credencialVerificada,
                $this->horaEntrega,
                $this->horaDevolucionAcordada,
                $this->receptorTipo === 'estudiante'
            );

            session()->flash('mensaje', "¡Despacho confirmado exitosamente! Entrega registrada y stock actualizado en kardex.");
            $this->mostrarModalEntrega = false;
        } catch (EquipoNoDisponibleException|StockInsuficienteException|StockReservadoClasesException|OperacionDuplicadaException $e) {
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

    public function abrirModalPresencial(): void
    {
        $this->resetValidation();
        $this->errorModalPresencial = null;
        $this->presencialNombre = '';
        $this->presencialRut = '';
        $this->presencialCredencialVerificada = false;
        $this->presencialHoraDevolucion = now()->addHours(3)->format('H:i');
        $primerLab = \App\Models\Ubicacion::where('tipo', 'laboratorio')->first() ?? \App\Models\Ubicacion::first();
        $this->presencialUbicacionId = $primerLab?->id;
        $this->presencialTipoDestino = 'laboratorio';
        $this->presencialEdificioDestino = 'Edificio A';
        $this->presencialSalaDestino = 'A101';
        $this->presencialAsignaturaId = null;
        $this->presencialObservaciones = '';
        $this->presencialItems = [];
        $this->presencialBusqueda = '';
        $this->mostrarModalPresencial = true;
    }

    public function seleccionarTipoDestinoPresencial(string $tipo): void
    {
        $this->presencialTipoDestino = $tipo;
        if ($tipo === 'sala') {
            $this->presencialEdificioDestino = 'Edificio A';
            $this->presencialSalaDestino = 'A101';
            $this->presencialUbicacionId = null;
        } else {
            $primerLab = \App\Models\Ubicacion::where('tipo', 'laboratorio')->first() ?? \App\Models\Ubicacion::first();
            $this->presencialUbicacionId = $primerLab?->id;
            $this->presencialEdificioDestino = 'Edificio A';
        }
    }

    public function updatedPresencialEdificioDestino(string $valor): void
    {
        if ($valor === 'Edificio A') {
            $this->presencialSalaDestino = 'A101';
        } else {
            $this->presencialSalaDestino = 'C101';
        }
    }

    public function laboratoriosDisponibles()
    {
        return \App\Models\Ubicacion::where('tipo', 'laboratorio')->orderBy('nombre')->get();
    }

    public function agregarItemPresencial(string $tipo, int $id): void
    {
        $this->errorModalPresencial = null;

        foreach ($this->presencialItems as $item) {
            if ($item['tipo'] === $tipo && $item['id'] === $id) {
                $this->errorModalPresencial = 'Este ítem ya ha sido agregado a la lista del préstamo.';
                return;
            }
        }

        if ($tipo === 'equipo') {
            $equipo = Equipo::find($id);
            if (! $equipo) {
                $this->errorModalPresencial = 'El equipo seleccionado no existe.';
                return;
            }

            if ($equipo->estado !== 'disponible') {
                $this->errorModalPresencial = "El equipo '{$equipo->nombre}' no está disponible (estado actual: {$equipo->estado}).";
                return;
            }

            if ($equipo->reservado_clases) {
                $this->errorModalPresencial = "El equipo '{$equipo->nombre}' está marcado como exclusivo para clases docentes y no puede prestarse a estudiantes.";
                return;
            }

            $stockMinimoClasesDefault = (int) ParametroSistema::get('stock_minimo_clases_default', 1);
            $disponiblesMismoTipo = Equipo::where('nombre', $equipo->nombre)->where('estado', 'disponible')->count();

            if ($disponiblesMismoTipo <= $stockMinimoClasesDefault) {
                $this->errorModalPresencial = "Stock reservado para clases: Quedan {$disponiblesMismoTipo} unidad(es) de '{$equipo->nombre}', reservadas para talleres docentes (umbral mínimo: {$stockMinimoClasesDefault}). No es posible prestar a estudiantes.";
                return;
            }

            $this->presencialItems[] = [
                'tipo' => 'equipo',
                'id' => $equipo->id,
                'nombre' => $equipo->nombre . ' (Serie: ' . $equipo->numero_serie . ')',
                'codigo' => $equipo->codigo_barras,
                'cantidad' => 1.0,
                'es_retornable' => true,
            ];
        } elseif ($tipo === 'insumo') {
            $insumo = Insumo::find($id);
            if (! $insumo) {
                $this->errorModalPresencial = 'El insumo seleccionado no existe.';
                return;
            }

            if ((float) $insumo->stock_actual <= 0) {
                $this->errorModalPresencial = "El insumo '{$insumo->nombre}' no tiene existencias físicas disponibles en pañol.";
                return;
            }

            if ($insumo->es_retornable) {
                $stockMinimoClases = (float) ($insumo->stock_minimo_clases ?? 2);
                if (((float) $insumo->stock_actual - $stockMinimoClases) < 1.0) {
                    $this->errorModalPresencial = "Stock reservado para clases: El insumo retornable '{$insumo->nombre}' cuenta con {$insumo->stock_actual} unidades, de las cuales {$stockMinimoClases} están reservadas para docencia. No es posible prestar a estudiantes.";
                    return;
                }
            }

            $this->presencialItems[] = [
                'tipo' => 'insumo',
                'id' => $insumo->id,
                'nombre' => $insumo->nombre,
                'codigo' => $insumo->codigo_barras,
                'cantidad' => 1.0,
                'es_retornable' => (bool) $insumo->es_retornable,
            ];
        }
    }

    public function removerItemPresencial(int $indice): void
    {
        $this->errorModalPresencial = null;
        if (isset($this->presencialItems[$indice])) {
            unset($this->presencialItems[$indice]);
            $this->presencialItems = array_values($this->presencialItems);
        }
    }

    public function confirmarPrestamoPresencial(PrestamoService $prestamoService): void
    {
        Gate::authorize('create', Entrega::class);
        $this->errorModalPresencial = null;

        $validationRules = [
            'presencialNombre' => 'required|string|min:3',
            'presencialRut' => 'required|string|min:4',
            'presencialCredencialVerificada' => 'accepted',
            'presencialHoraDevolucion' => 'required|string',
            'presencialTipoDestino' => 'required|in:sala,laboratorio',
            'presencialItems' => 'required|array|min:1',
            'presencialItems.*.cantidad' => 'required|numeric|min:1',
        ];

        if ($this->presencialTipoDestino === 'sala') {
            $validationRules['presencialEdificioDestino'] = 'required|in:Edificio A,Edificio C';
            $validationRules['presencialSalaDestino'] = 'required|string|min:2';
        } else {
            $validationRules['presencialUbicacionId'] = 'required|exists:ubicaciones,id';
        }

        $this->validate($validationRules, [
            'presencialNombre.required' => 'El nombre del estudiante es obligatorio.',
            'presencialRut.required' => 'El RUT o credencial institucional del estudiante es obligatorio.',
            'presencialCredencialVerificada.accepted' => 'Debe confirmar haber verificado y retenido físicamente la credencial del estudiante.',
            'presencialHoraDevolucion.required' => 'Debe registrar la hora acordada para la devolución.',
            'presencialTipoDestino.required' => 'Debe indicar si el destino es SALA o LABORATORIO.',
            'presencialEdificioDestino.required' => 'El edificio de destino es obligatorio.',
            'presencialSalaDestino.required' => 'La sala de destino es obligatoria.',
            'presencialUbicacionId.required' => 'Debe seleccionar un laboratorio de destino.',
            'presencialItems.min' => 'Debe agregar al menos un equipo o insumo para entregar.',
        ]);

        $ubicacionDestinoId = null;
        $espacioDestino = '';

        if ($this->presencialTipoDestino === 'sala') {
            $espacioDestino = "{$this->presencialEdificioDestino} - {$this->presencialSalaDestino}";
        } else {
            $lab = \App\Models\Ubicacion::find($this->presencialUbicacionId);
            $ubicacionDestinoId = $lab?->id;
            $espacioDestino = $lab ? $lab->nombre : 'Laboratorio';
        }

        try {
            $prestamoService->registrarPrestamoPresencialEnMeson(
                Auth::user(),
                [
                    'nombre' => $this->presencialNombre,
                    'rut' => \App\Services\FormatoHelper::formatearRut($this->presencialRut) ?? $this->presencialRut,
                    'credencial_verificada' => $this->presencialCredencialVerificada,
                    'hora_devolucion_acordada' => $this->presencialHoraDevolucion,
                    'hora_entrega' => now()->format('H:i:s'),
                    'tipo_destino' => $this->presencialTipoDestino,
                    'edificio_destino' => $this->presencialTipoDestino === 'sala' ? $this->presencialEdificioDestino : 'Edificio A',
                    'espacio_destino' => $espacioDestino,
                    'ubicacion_destino_id' => $ubicacionDestinoId,
                    'asignatura_id' => $this->presencialAsignaturaId,
                    'observaciones' => $this->presencialObservaciones,
                ],
                $this->presencialItems
            );

            session()->flash('mensaje', "¡Préstamo presencial despachado exitosamente al alumno {$this->presencialNombre}! Material registrado en préstamos activos.");
            $this->mostrarModalPresencial = false;
            $this->pestanaActiva = 'abiertas';
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorModalPresencial = collect($e->errors())->flatten()->first();
        } catch (EquipoNoDisponibleException|StockInsuficienteException|StockReservadoClasesException|OperacionDuplicadaException $e) {
            $this->errorModalPresencial = $e->getMessage();
        } catch (\Exception $e) {
            $this->errorModalPresencial = 'Ocurrió un error al procesar el préstamo presencial: ' . $e->getMessage();
        }
    }

    public function catalogoDisponiblePresencial(): array
    {
        $busqueda = trim($this->presencialBusqueda);

        $queryEquipos = Equipo::where('estado', 'disponible')->orderBy('nombre');
        $queryInsumos = Insumo::where('stock_actual', '>', 0)->orderBy('nombre');

        if ($busqueda !== '') {
            $bLower = mb_strtolower($busqueda);
            $queryEquipos->where(fn ($q) => $q->whereRaw('LOWER(nombre) LIKE ?', ["%{$bLower}%"])->orWhereRaw('LOWER(codigo_barras) LIKE ?', ["%{$bLower}%"]));
            $queryInsumos->where(fn ($q) => $q->whereRaw('LOWER(nombre) LIKE ?', ["%{$bLower}%"])->orWhereRaw('LOWER(codigo_barras) LIKE ?', ["%{$bLower}%"]));
        }

        $equipos = $queryEquipos->take(12)->get();
        $insumos = $queryInsumos->take(12)->get();

        $stockMinimoClasesDefault = (int) ParametroSistema::get('stock_minimo_clases_default', 1);

        $equipos->transform(function ($eq) use ($stockMinimoClasesDefault) {
            $disponibles = Equipo::where('nombre', $eq->nombre)->where('estado', 'disponible')->count();
            $eq->bloqueado_para_estudiante = $eq->reservado_clases || ($disponibles <= $stockMinimoClasesDefault);
            $eq->motivo_bloqueo = $eq->reservado_clases
                ? 'Exclusivo Docencia'
                : ($disponibles <= $stockMinimoClasesDefault ? "Reserva clases ({$disponibles} unid.)" : null);

            return $eq;
        });

        $insumos->transform(function ($ins) {
            if ($ins->es_retornable) {
                $reserva = (float) ($ins->stock_minimo_clases ?? 2);
                $ins->bloqueado_para_estudiante = ((float) $ins->stock_actual - $reserva) < 1.0;
                $ins->motivo_bloqueo = $ins->bloqueado_para_estudiante ? "Reserva clases ({$ins->stock_actual}/{$reserva})" : null;
            } else {
                $ins->bloqueado_para_estudiante = false;
                $ins->motivo_bloqueo = null;
            }

            return $ins;
        });

        return [
            'equipos' => $equipos,
            'insumos' => $insumos,
        ];
    }
}; ?>

<div class="py-6 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <!-- Encabezado del Módulo Pañolero (Ponytail: Simplicidad Activa) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-7 h-7 text-red-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
                <span>Préstamos</span>
                <span class="sr-only">Gestión de Mesón: Despacho y Devoluciones</span>
            </h1>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <!-- Escáner Rápido de Código de Barras (Hardware 1D / Teclado) -->
            <div class="w-full sm:w-72 bg-white p-1.5 rounded-xl border border-slate-200 shadow-2xs">
                <form wire:submit.prevent="escanearCodigo" class="flex gap-1.5">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            wire:model="escaneoCodigoBarras"
                            placeholder="Escanear código..."
                            class="w-full text-xs py-1 px-2.5 border-slate-200 rounded-lg focus:ring-red-500 focus:border-red-500"
                        />
                    </div>
                    <button type="submit" class="px-2.5 py-1 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-black transition">
                        Verificar
                    </button>
                </form>
            </div>

            <!-- Botón Préstamo Presencial en Mesón a Estudiantes -->
            <button
                wire:click="abrirModalPresencial"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-bold text-xs sm:text-sm rounded-xl shadow-2xs hover:shadow transition active:scale-95 shrink-0"
            >
                <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Préstamo Alumno</span>
            </button>
        </div>
    </div>

    @if($resultadoEscaneo)
        <div class="mb-4 text-xs p-3 bg-blue-50 text-blue-800 rounded-xl border border-blue-200 flex items-center justify-between">
            <span>{{ $resultadoEscaneo }}</span>
            <button wire:click="$set('resultadoEscaneo', null)" class="text-blue-600 font-bold ml-2">&times;</button>
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

    <!-- Barra de Control: Pestañas + Buscador Integrado -->
    @php
        $totalAbiertas = $this->conteoAbiertas();
        $totalVencidas = $this->conteoVencidas();
    @endphp

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6 bg-white p-2 sm:p-2.5 rounded-2xl border border-slate-200/90 shadow-2xs">
        <!-- Pestañas (Pills) -->
        <div class="flex items-center gap-1.5 overflow-x-auto p-1 bg-slate-100/80 rounded-xl">
            <button
                type="button"
                wire:click="$set('pestanaActiva', 'abiertas')"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold rounded-lg transition {{ in_array($pestanaActiva, ['abiertas', 'solicitudes', 'activos']) ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50' }}"
            >
                <span>Abiertas</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold {{ in_array($pestanaActiva, ['abiertas', 'solicitudes', 'activos']) ? 'bg-red-100 text-red-700' : 'bg-slate-200 text-slate-700' }}">
                    {{ $totalAbiertas }}
                </span>
            </button>

            <button
                type="button"
                wire:click="$set('pestanaActiva', 'vencidas')"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold rounded-lg transition {{ $pestanaActiva === 'vencidas' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50' }}"
            >
                <span>Vencidas</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold {{ $totalVencidas > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' }}">
                    {{ $totalVencidas }}
                </span>
            </button>

            <button
                type="button"
                wire:click="$set('pestanaActiva', 'historial')"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold rounded-lg transition {{ $pestanaActiva === 'historial' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50' }}"
            >
                <span>Historial</span>
            </button>
        </div>

        <!-- Buscador Reactivo Integrado -->
        <div class="relative w-full md:w-80">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="busquedaRapida"
                placeholder="Buscar por persona, credencial o ítem..."
                class="w-full text-xs py-2 pl-9 pr-8 border-slate-200 rounded-xl bg-slate-50/50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-red-600 focus:border-red-600 transition placeholder:text-slate-400"
            />
            @if($busquedaRapida !== '')
                <button
                    type="button"
                    wire:click="$set('busquedaRapida', '')"
                    class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif
        </div>
    </div>

    <!-- PESTAÑA 1: ABIERTAS -->
    @if(in_array($pestanaActiva, ['abiertas', 'solicitudes', 'activos']))
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Solicitudes y Préstamos Abiertos</h2>
                    <span class="text-xs text-slate-500 font-normal">Pedidos docentes listos para entrega directa en sala/laboratorio y préstamos en curso.</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 font-medium">Estado:</span>
                    <select
                        wire:model.live="filtroEstado"
                        class="text-xs border-slate-300 rounded-lg focus:ring-red-500 focus:border-red-500 py-1.5 bg-slate-50"
                    >
                        <option value="todas">Todos los estados</option>
                        <option value="pendientes">Pendientes</option>
                        <option value="aprobadas">Aprobadas</option>
                        <option value="entregadas_abiertas">Préstamo Abierto</option>
                    </select>
                </div>
            </div>

            @php $solicitudesPaginadas = $this->solicitudesAbiertas(); @endphp

            @if($solicitudesPaginadas->isEmpty())
                <div class="text-center py-12 text-slate-400 text-xs">
                    No se encontraron solicitudes o préstamos abiertos con los filtros aplicados.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($solicitudesPaginadas as $sol)
                        <div class="border border-slate-200 rounded-xl p-4 hover:border-slate-300 transition bg-white shadow-2xs">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 text-sm">#SOL-{{ str_pad($sol->id, 5, '0', STR_PAD_LEFT) }}</span>
                                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full
                                            {{ match($sol->estado) {
                                                'pendiente' => 'bg-amber-100 text-amber-800',
                                                'aprobada' => 'bg-blue-100 text-blue-800',
                                                'entregada_abierta' => 'bg-purple-100 text-purple-800',
                                                'cerrada' => 'bg-emerald-100 text-emerald-800',
                                                'rechazada' => 'bg-red-100 text-red-800',
                                                'sin_disponibilidad' => 'bg-slate-100 text-slate-700',
                                                default => 'bg-slate-100 text-slate-800',
                                            } }}">
                                            {{ strtoupper($sol->estado) }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        Solicitante: <strong class="text-slate-800">{{ $sol->solicitante->name }}</strong> ({{ $sol->solicitante->email }}) &bull;
                                        Fecha: {{ $sol->fecha_solicitud->format('d/m/Y H:i') }} &bull;
                                        Requerida: <strong class="text-slate-800">{{ $sol->fecha_requerida->format('d/m/Y') }}</strong>
                                    </div>

                                    <!-- Tags de Ubicación / Asignatura / Hora (Formulario Nuevo) -->
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        @if($sol->hora_requerida)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 text-amber-900 border border-amber-200 text-[11px] font-bold">
                                                <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Hora: {{ \Carbon\Carbon::parse($sol->hora_requerida)->format('H:i') }}
                                            </span>
                                        @endif
                                        @if($sol->espacio_destino)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-900 border border-blue-200 text-[11px] font-bold">
                                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                                Destino: {{ $sol->espacio_destino }}
                                            </span>
                                        @elseif($sol->ubicacionDestino)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-900 border border-blue-200 text-[11px] font-bold">
                                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                                Destino: {{ $sol->ubicacionDestino->nombre }}
                                            </span>
                                        @endif
                                        @if($sol->asignatura)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-purple-50 text-purple-900 border border-purple-200 text-[11px] font-bold">
                                                <svg class="w-3 h-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                                Taller: {{ $sol->asignatura->codigo }} - {{ $sol->asignatura->nombre }}
                                            </span>
                                        @elseif($sol->observaciones)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-200 text-[11px] font-medium">
                                                Actividad: {{ \Illuminate\Support\Str::limit($sol->observaciones, 45) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Acción Directa: Entregar (Sin requerir aprobación intermedia) -->
                                <div class="flex items-center gap-2">
                                    @php
                                        $tienePendientesEntrega = $sol->detalles->contains(fn ($d) => (float)$d->cantidad_entregada < (float)$d->cantidad_solicitada);
                                    @endphp

                                    @if(in_array($sol->estado, ['pendiente', 'aprobada', 'entregada_abierta']) && $tienePendientesEntrega)
                                        <button
                                            wire:click="abrirModalEntrega({{ $sol->id }})"
                                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-2xs"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span>Entregar</span>
                                        </button>
                                    @endif

                                    @if($sol->estado === 'pendiente')
                                        <button
                                            wire:click="abrirModalRechazo({{ $sol->id }})"
                                            class="px-2.5 py-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg text-xs font-medium transition"
                                            title="Rechazar solicitud"
                                        >
                                            Rechazar
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Listado de ítems de la solicitud -->
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider text-[10px]">
                                        <tr>
                                            <th class="py-1.5 px-3">Tipo</th>
                                            <th class="py-1.5 px-3">Ítem / Código</th>
                                            <th class="py-1.5 px-3 text-center">Solicitado</th>
                                            <th class="py-1.5 px-3 text-center">Entregado</th>
                                            <th class="py-1.5 px-3 text-center">Devuelto</th>
                                            <th class="py-1.5 px-3 text-center">Estado Devolución</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($sol->detalles as $det)
                                            <tr>
                                                <td class="py-2 px-3">
                                                    @if($det->equipo)
                                                        <span class="px-2 py-0.5 rounded text-[10px] bg-blue-100 text-blue-700 font-semibold">Equipo Retornable</span>
                                                    @elseif($det->insumo && $det->insumo->es_retornable)
                                                        <span class="px-2 py-0.5 rounded text-[10px] bg-indigo-100 text-indigo-700 font-semibold">Insumo Retornable</span>
                                                    @else
                                                        <span class="px-2 py-0.5 rounded text-[10px] bg-slate-100 text-slate-700 font-semibold">Insumo Consumible</span>
                                                    @endif
                                                </td>
                                                <td class="py-2 px-3 font-medium text-slate-800">
                                                    {{ $det->equipo->nombre ?? $det->insumo->nombre }}
                                                    <span class="text-slate-400 font-mono text-[11px]">({{ $det->equipo->codigo_barras ?? $det->insumo->codigo_barras }})</span>
                                                </td>
                                                <td class="py-2 px-3 text-center font-bold">{{ (float)$det->cantidad_solicitada }}</td>
                                                <td class="py-2 px-3 text-center font-bold {{ (float)$det->cantidad_entregada > 0 ? 'text-blue-600' : 'text-slate-400' }}">
                                                    {{ (float)$det->cantidad_entregada }}
                                                </td>
                                                <td class="py-2 px-3 text-center font-bold {{ (float)$det->cantidad_devuelta > 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                                                    {{ (float)$det->cantidad_devuelta }}
                                                </td>
                                                <td class="py-2 px-3 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold
                                                        {{ match($det->estado_devolucion) {
                                                            'no_aplica' => 'bg-slate-100 text-slate-600',
                                                            'pendiente' => 'bg-purple-100 text-purple-700',
                                                            'parcial' => 'bg-amber-100 text-amber-700',
                                                            'completa' => 'bg-emerald-100 text-emerald-700',
                                                            'con_danos' => 'bg-red-100 text-red-700',
                                                            default => 'bg-slate-100 text-slate-600',
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

    <!-- PESTAÑA 2: VENCIDAS (Exclusivo Alumnos) -->
    @if($pestanaActiva === 'vencidas')
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-4 sm:p-6">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Préstamos Presenciales Vencidos</h2>
                <span class="text-xs text-slate-500 font-normal">Préstamos de alumnos cuya hora acordada ha expirado y tienen materiales pendientes de devolución.</span>
            </div>

            @php $vencidasPaginadas = $this->prestamosVencidos(); @endphp

            @if($vencidasPaginadas->isEmpty())
                <div class="text-center py-12 text-slate-400 text-xs">
                    No hay préstamos presenciales con hora límite vencida en este momento.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($vencidasPaginadas as $ent)
                        <div class="border border-red-200 bg-red-50/20 rounded-xl p-4 shadow-2xs">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-red-100 pb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 text-sm">#ENT-{{ str_pad($ent->id, 5, '0', STR_PAD_LEFT) }}</span>
                                        <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-red-100 text-red-800 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            VENCIDO (Límite: {{ \Carbon\Carbon::parse($ent->hora_devolucion_acordada)->format('H:i') }} hrs)
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-600 mt-1 flex flex-wrap items-center gap-2">
                                        <span>Alumno: <strong class="text-slate-900">{{ $ent->receptor_nombre }}</strong></span>
                                        @if($ent->receptor_credencial_rut)
                                            <span>&bull; RUT: <strong class="font-mono text-slate-800">{{ $ent->receptor_credencial_rut }}</strong></span>
                                        @endif
                                        @if($ent->credencial_verificada)
                                            <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-900 font-bold text-[10px]">Credencial física retenida</span>
                                        @endif
                                        <span>&bull; Entregado: {{ $ent->fecha_entrega->format('d/m/Y H:i') }}</span>
                                    </div>
                                </div>

                                <button
                                    wire:click="abrirModalDevolucion({{ $ent->id }})"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-2xs flex items-center justify-center gap-1.5 shrink-0"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    <span>Recibir Devolución</span>
                                </button>
                            </div>

                            <!-- Ítems Pendientes -->
                            <div class="mt-3">
                                <ul class="text-xs space-y-1">
                                    @foreach($ent->solicitud->detalles as $det)
                                        @php
                                            $entregada = (float)$det->cantidad_entregada;
                                            $devuelta = (float)$det->cantidad_devuelta;
                                            $pendiente = $entregada - $devuelta;
                                        @endphp
                                        @if($pendiente > 0 && ($det->equipo_id !== null || ($det->insumo && $det->insumo->es_retornable)))
                                            <li class="flex items-center justify-between p-2 rounded-lg bg-white border border-red-100">
                                                <div>
                                                    <span class="font-semibold text-slate-900">{{ $det->equipo->nombre ?? $det->insumo->nombre }}</span>
                                                    <span class="text-slate-400 font-mono text-[11px]">({{ $det->equipo->codigo_barras ?? $det->insumo->codigo_barras }})</span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-slate-500">Pendiente: </span>
                                                    <span class="font-bold text-red-700">{{ $pendiente }} unidad(es)</span>
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $vencidasPaginadas->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- PESTAÑA 3: HISTORIAL -->
    @if($pestanaActiva === 'historial')
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-4 sm:p-6">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Historial de Entregas y Devoluciones</h2>
                <span class="text-xs text-slate-500 font-normal">Auditoría completa de movimientos, receptores y encargados de pañol.</span>
            </div>

            @php $historial = $this->historialEntregas(); @endphp

            @if($historial->isEmpty())
                <div class="text-center py-12 text-slate-400 text-xs">
                    No hay registros históricos disponibles.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-600 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-2 px-3">Folio Entrega</th>
                                <th class="py-2 px-3">Fecha y Hora</th>
                                <th class="py-2 px-3">Receptor</th>
                                <th class="py-2 px-3">Tipo</th>
                                <th class="py-2 px-3">Despachador</th>
                                <th class="py-2 px-3">Devoluciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($historial as $h)
                                <tr>
                                    <td class="py-2 px-3 font-bold text-slate-900">#ENT-{{ str_pad($h->id, 5, '0', STR_PAD_LEFT) }}</td>
                                    <td class="py-2 px-3 text-slate-600">{{ $h->fecha_entrega->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 px-3 font-semibold text-slate-800">{{ $h->receptor_nombre }}</td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $h->receptor_tipo === 'docente' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ ucfirst($h->receptor_tipo) }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-slate-600">{{ $h->entregador->name }}</td>
                                    <td class="py-2 px-3">
                                        @if($h->devoluciones->isEmpty())
                                            <span class="text-slate-400 italic">Sin devoluciones</span>
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
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirmar Entrega de Materiales
                    </h3>
                    <button wire:click="$set('mostrarModalEntrega', false)" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto text-xs">
                    <!-- Datos del Receptor -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nombre Completo del Receptor <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                wire:model="receptorNombre"
                                placeholder="Ej. Juan Pérez González"
                                class="w-full text-xs border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                            />
                            @error('receptorNombre')
                                <p class="text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Tipo de Receptor <span class="text-red-500">*</span></label>
                            <select
                                wire:model.live="receptorTipo"
                                class="w-full text-xs border-slate-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="docente">Docente Institucional</option>
                                <option value="estudiante">Estudiante en Práctica / Taller</option>
                            </select>
                            @error('receptorTipo')
                                <p class="text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @if($receptorTipo === 'estudiante')
                        <div class="p-3 bg-red-50/70 border border-red-200 rounded-xl space-y-3">
                            <div class="font-bold text-red-900 text-xs flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                                Validación de Credencial y Compromiso Horario del Estudiante
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">RUT o N° Credencial del Estudiante <span class="text-red-500">*</span></label>
                                    <input
                                        type="text"
                                        wire:model.live.blur="receptorCredencialRut"
                                        maxlength="12"
                                        oninput="let v = this.value.replace(/[^0-9kK]/g, ''); if(v.length > 9) v = v.slice(0, 9); if(v.length >= 2) { let c = v.slice(0, -1).replace(/\D/g, ''); let dv = v.slice(-1).toUpperCase(); this.value = (c ? new Intl.NumberFormat('es-CL').format(c) : '') + '-' + dv; } else { this.value = v.toUpperCase(); }"
                                        placeholder="Ej. 20.123.456-7"
                                        class="w-full text-xs font-mono border-slate-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                    />
                                    @error('receptorCredencialRut') <p class="text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Hora Límite de Devolución Acordada <span class="text-red-500">*</span></label>
                                    <input
                                        type="time"
                                        wire:model="horaDevolucionAcordada"
                                        class="w-full text-xs border-slate-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                    />
                                    @error('horaDevolucionAcordada') <p class="text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="credencialVerificada"
                                        class="rounded text-red-600 focus:ring-red-600"
                                    />
                                    <span class="text-xs font-semibold text-slate-800">
                                        Confirmo haber verificado y retenido físicamente la credencial institucional del estudiante en mesón <span class="text-red-500">*</span>
                                    </span>
                                </label>
                                @error('credencialVerificada') <p class="text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    <!-- Selección de Ítems a Entregar -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Ítems a Entregar en este Despacho:</label>
                        @php
                            $solicitudModal = SolicitudMaterial::with('detalles.insumo', 'detalles.equipo')->find($solicitudEntregaId);
                        @endphp
                        @if($solicitudModal)
                            <div class="space-y-2 border border-slate-200 rounded-xl p-3 bg-slate-50/50">
                                @foreach($solicitudModal->detalles as $det)
                                    @php
                                        $pendiente = (float)$det->cantidad_solicitada - (float)$det->cantidad_entregada;
                                    @endphp
                                    @if($pendiente > 0)
                                        <label class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200 cursor-pointer hover:bg-slate-50">
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    wire:model="detallesSeleccionadosIds"
                                                    value="{{ $det->id }}"
                                                    class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                />
                                                <div>
                                                    <span class="font-medium text-slate-900">{{ $det->equipo->nombre ?? $det->insumo->nombre }}</span>
                                                    <span class="text-slate-400 font-mono text-[10px]">({{ $det->equipo->codigo_barras ?? $det->insumo->codigo_barras }})</span>
                                                    <div class="text-[10px] text-slate-500">
                                                        {{ $det->equipo ? 'Equipo Retornable' : ($det->insumo->es_retornable ? 'Insumo Retornable' : 'Insumo Consumible (Salida definitiva)') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-bold text-slate-800">{{ $pendiente }} un.</span>
                                            </div>
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('mostrarModalEntrega', false)"
                        class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-lg transition"
                    >
                        Cancelar
                    </button>
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
                                            <input type="text" disabled value="1 unidad" class="w-full text-xs py-1 px-2 bg-slate-100 border-slate-300 rounded text-slate-600 font-semibold" />
                                        @else
                                            <input
                                                type="number"
                                                step="1"
                                                min="0"
                                                max="{{ $item['max'] }}"
                                                wire:model="itemsDevolucionForm.{{ $detId }}.cantidad"
                                                class="w-full text-xs py-1 px-2 border-slate-300 rounded"
                                            />
                                        @endif
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-700 mb-0.5">Estado Físico:</label>
                                        <select
                                            wire:model.live="itemsDevolucionForm.{{ $detId }}.conforme"
                                            class="w-full text-xs py-1 px-2 border-slate-300 rounded {{ ! $item['conforme'] ? 'bg-red-50 text-red-800 font-bold border-red-300' : 'bg-emerald-50 text-emerald-800 font-bold border-emerald-300' }}"
                                        >
                                            <option value="1">Buen Estado (Conforme)</option>
                                            <option value="0">Con Daños / Falla Física</option>
                                        </select>
                                    </div>
                                </div>

                                @if(! $item['conforme'])
                                    <div class="mt-2 p-2 bg-red-50 rounded-lg border border-red-200">
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

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Observaciones Generales de la Devolución:</label>
                        <textarea
                            wire:model="observacionesDevolucion"
                            rows="2"
                            placeholder="Comentarios adicionales sobre la recepción en pañol..."
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
                    <h3 class="text-sm font-bold">Rechazar Solicitud #SOL-{{ $solicitudRechazoId }}</h3>
                    <button wire:click="$set('mostrarModalRechazo', false)" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-3 text-xs">
                    <label class="block font-semibold text-slate-700">Motivo del Rechazo:</label>
                    <textarea
                        wire:model="motivoRechazo"
                        rows="3"
                        placeholder="Indique el motivo por el cual no se puede atender la solicitud..."
                        class="w-full text-xs border-slate-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                    ></textarea>
                </div>
                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button wire:click="$set('mostrarModalRechazo', false)" class="px-3 py-1.5 text-xs text-slate-600">Cancelar</button>
                    <button wire:click="confirmarRechazo" class="px-4 py-1.5 bg-red-600 text-white font-bold rounded-lg text-xs hover:bg-red-700">Confirmar Rechazo</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: Nuevo Préstamo Presencial en Mesón a Estudiantes (CP-25, CP-26, Sección 3.4) -->
    @if($mostrarModalPresencial)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-5 py-4 bg-red-700 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        <span>Atención de Ventanilla: Préstamo Presencial a Estudiante</span>
                    </h3>
                    <button wire:click="$set('mostrarModalPresencial', false)" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5 space-y-5 max-h-[80vh] overflow-y-auto text-xs">
                    @if($errorModalPresencial)
                        <div class="p-3 bg-red-50 border-l-4 border-red-600 rounded-r-xl text-red-900 flex items-start justify-between gap-3 shadow-xs animate-in fade-in duration-150">
                            <div class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <div>
                                    <p class="font-bold text-red-800 text-xs">Atención (Operación no permitida):</p>
                                    <p class="text-[11px] text-red-700 mt-0.5 leading-relaxed font-medium">{{ $errorModalPresencial }}</p>
                                </div>
                            </div>
                            <button type="button" wire:click="$set('errorModalPresencial', null)" class="text-red-400 hover:text-red-700 font-bold p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endif

                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 flex items-start gap-2.5">
                        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="space-y-0.5">
                            <span class="font-bold">Regla de Negocio (Estudiantes sin cuenta):</span>
                            <p class="text-[11px] text-amber-800">
                                Los alumnos retiran materiales presencialmente en el mesón. Es obligatorio exigir y retener su credencial institucional física, registrar su RUT y pactar una hora límite de devolución. El sistema bloqueará la entrega si el stock disponible invade la reserva mínima para clases docentes.
                            </p>
                        </div>
                    </div>

                    <!-- SECCIÓN 1: Datos del Estudiante Receptor -->
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/60 space-y-3">
                        <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-red-600"></span>
                            1. Identificación del Estudiante y Retención de Credencial
                        </h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Nombre Completo del Estudiante <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    wire:model="presencialNombre"
                                    placeholder="Ej: Matías Morales Araya"
                                    class="w-full text-xs border-slate-300 rounded-lg focus:ring-red-600 focus:border-red-600"
                                />
                                @error('presencialNombre') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">RUT / Credencial del Estudiante <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    wire:model.live.blur="presencialRut"
                                    maxlength="12"
                                    oninput="let v = this.value.replace(/[^0-9kK]/g, ''); if(v.length > 9) v = v.slice(0, 9); if(v.length >= 2) { let c = v.slice(0, -1).replace(/\D/g, ''); let dv = v.slice(-1).toUpperCase(); this.value = (c ? new Intl.NumberFormat('es-CL').format(c) : '') + '-' + dv; } else { this.value = v.toUpperCase(); }"
                                    placeholder="Ej: 20.345.678-9"
                                    class="w-full text-xs font-mono border-slate-300 rounded-lg focus:ring-red-600 focus:border-red-600"
                                />
                                @error('presencialRut') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="presencialCredencialVerificada"
                                    class="mt-0.5 rounded border-red-400 text-red-600 focus:ring-red-500"
                                />
                                <span class="font-bold text-red-900 text-xs leading-snug">
                                    Confirmo haber verificado y retenido físicamente la credencial institucional del estudiante en el mesón de pañol. <span class="text-red-600">*</span>
                                </span>
                            </label>
                            @error('presencialCredencialVerificada') <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Hora Acordada de Devolución <span class="text-red-500">*</span></label>
                            <input
                                type="time"
                                wire:model="presencialHoraDevolucion"
                                class="w-full text-xs border-slate-300 rounded-lg focus:ring-red-600 focus:border-red-600 font-mono"
                            />
                            @error('presencialHoraDevolucion') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <!-- Selector Multinivel de Destino (SALA vs LABORATORIO) -->
                        <div class="p-3 bg-white rounded-xl border border-slate-200 space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                                    Sala / Laboratorio de Destino <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        wire:click="seleccionarTipoDestinoPresencial('sala')"
                                        class="py-2 px-3 text-xs font-semibold rounded-lg border text-center transition {{ $presencialTipoDestino === 'sala' ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' }}"
                                    >
                                        SALA
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="seleccionarTipoDestinoPresencial('laboratorio')"
                                        class="py-2 px-3 text-xs font-semibold rounded-lg border text-center transition {{ $presencialTipoDestino === 'laboratorio' ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' }}"
                                    >
                                        LABORATORIO
                                    </button>
                                </div>
                                @error('presencialTipoDestino')
                                    <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>

                            @if($presencialTipoDestino === 'sala')
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-gray-200/70">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                            Edificio <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            wire:model.live="presencialEdificioDestino"
                                            class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                        >
                                            <option value="Edificio A">Edificio A</option>
                                            <option value="Edificio C">Edificio C</option>
                                        </select>
                                        @error('presencialEdificioDestino')
                                            <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                            Sala ({{ $presencialEdificioDestino }}) <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            wire:model="presencialSalaDestino"
                                            class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                        >
                                            @if($presencialEdificioDestino === 'Edificio A')
                                                @foreach($salasEdificioA as $sala)
                                                    <option value="{{ $sala }}">{{ $sala }}</option>
                                                @endforeach
                                            @else
                                                @foreach($salasEdificioC as $sala)
                                                    <option value="{{ $sala }}">{{ $sala }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                        @error('presencialSalaDestino')
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
                                        wire:model="presencialUbicacionId"
                                        class="w-full text-xs border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                                    >
                                        @foreach($this->laboratoriosDisponibles() as $lab)
                                            <option value="{{ $lab->id }}">{{ $lab->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('presencialUbicacionId')
                                        <p class="text-[11px] text-red-600 mt-1 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- SECCIÓN 2: Selección y Escaneo de Materiales -->
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/60 space-y-3">
                        <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-600"></span>
                                2. Materiales a Entregar al Estudiante
                            </span>
                            <span class="text-[11px] font-semibold text-slate-500">{{ count($presencialItems) }} ítem(s) agregado(s)</span>
                        </h4>

                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="presencialBusqueda"
                                placeholder="Buscar equipo o insumo para agregar (nombre o código de barras)..."
                                class="w-full text-xs pl-8 pr-3 py-1.5 border-slate-300 rounded-lg focus:ring-red-600 focus:border-red-600"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>

                        @php $cat = $this->catalogoDisponiblePresencial(); @endphp
                        @if($cat['equipos']->isNotEmpty() || $cat['insumos']->isNotEmpty())
                            <div class="border border-slate-200 rounded-lg bg-white p-2 max-h-40 overflow-y-auto divide-y divide-slate-100 shadow-2xs">
                                @foreach($cat['equipos'] as $eq)
                                    <div class="py-1.5 px-2 flex items-center justify-between hover:bg-slate-50 text-xs">
                                        <div class="flex-1 min-w-0 pr-2">
                                            <span class="font-semibold text-slate-800">{{ $eq->nombre }}</span>
                                            <span class="text-slate-400 text-[10px] ml-1">({{ $eq->codigo_barras }} · Serie: {{ $eq->numero_serie }})</span>
                                            <span class="text-[9px] bg-emerald-100 text-emerald-800 border border-emerald-300 px-1.5 py-0.5 rounded font-semibold ml-1">
                                                Disponible
                                            </span>
                                            @if($eq->bloqueado_para_estudiante)
                                                <span class="text-[9px] bg-amber-100 text-amber-800 border border-amber-300 px-1.5 py-0.5 rounded font-bold ml-1" title="Stock protegido para dictado de clases docentes (CP-25)">
                                                    ⚠️ {{ $eq->motivo_bloqueo }}
                                                </span>
                                            @endif
                                        </div>
                                        <button
                                            type="button"
                                            @if($eq->bloqueado_para_estudiante)
                                                disabled
                                                title="No disponible para estudiantes: reservado como stock mínimo para clases docentes"
                                                class="px-2 py-0.5 bg-slate-100 text-slate-400 cursor-not-allowed font-medium rounded text-[11px] transition shrink-0"
                                            @else
                                                wire:click="agregarItemPresencial('equipo', {{ $eq->id }})"
                                                class="px-2 py-0.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded text-[11px] transition shrink-0 active:scale-95"
                                            @endif
                                        >
                                            {{ $eq->bloqueado_para_estudiante ? 'Reservado' : '+ Agregar' }}
                                        </button>
                                    </div>
                                @endforeach

                                @foreach($cat['insumos'] as $ins)
                                    <div class="py-1.5 px-2 flex items-center justify-between hover:bg-slate-50 text-xs">
                                        <div class="flex-1 min-w-0 pr-2">
                                            <span class="font-semibold text-slate-800">{{ $ins->nombre }}</span>
                                            <span class="text-slate-400 text-[10px] ml-1">({{ $ins->codigo_barras }} · Stock: {{ $ins->stock_actual }} {{ $ins->unidad }})</span>
                                            <span class="text-[9px] px-1 py-0.5 rounded {{ $ins->es_retornable ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-700' }}">
                                                {{ $ins->es_retornable ? 'Retornable' : 'Consumible' }}
                                            </span>
                                            @if($ins->bloqueado_para_estudiante)
                                                <span class="text-[9px] bg-amber-100 text-amber-800 border border-amber-300 px-1.5 py-0.5 rounded font-bold ml-1" title="Stock protegido para dictado de clases docentes (CP-25)">
                                                    ⚠️ {{ $ins->motivo_bloqueo }}
                                                </span>
                                            @endif
                                        </div>
                                        <button
                                            type="button"
                                            @if($ins->bloqueado_para_estudiante)
                                                disabled
                                                title="No disponible para estudiantes: reservado como stock mínimo para clases docentes"
                                                class="px-2 py-0.5 bg-slate-100 text-slate-400 cursor-not-allowed font-medium rounded text-[11px] transition shrink-0"
                                            @else
                                                wire:click="agregarItemPresencial('insumo', {{ $ins->id }})"
                                                class="px-2 py-0.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded text-[11px] transition shrink-0 active:scale-95"
                                            @endif
                                        >
                                            {{ $ins->bloqueado_para_estudiante ? 'Reservado' : '+ Agregar' }}
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if(empty($presencialItems))
                            <div class="p-3 border-2 border-dashed border-slate-300 rounded-lg text-center text-slate-400 text-xs">
                                No hay materiales agregados. Busque arriba y haga clic en "+ Agregar".
                            </div>
                        @else
                            <div class="border border-slate-200 rounded-lg bg-white overflow-hidden shadow-2xs">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-slate-50 text-slate-600 uppercase tracking-wider text-[10px]">
                                        <tr>
                                            <th class="py-2 px-3">Material</th>
                                            <th class="py-2 px-3">Código</th>
                                            <th class="py-2 px-3 w-20">Cantidad</th>
                                            <th class="py-2 px-3 text-center w-12">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($presencialItems as $idx => $it)
                                            <tr>
                                                <td class="py-2 px-3 font-semibold text-slate-800">
                                                    {{ $it['nombre'] }}
                                                    <span class="text-[10px] block text-slate-400">
                                                        {{ $it['tipo'] === 'equipo' ? 'Equipo retornable' : ($it['es_retornable'] ? 'Insumo retornable' : 'Insumo consumible') }}
                                                    </span>
                                                </td>
                                                <td class="py-2 px-3 font-mono text-slate-500">{{ $it['codigo'] }}</td>
                                                <td class="py-2 px-3">
                                                    @if($it['tipo'] === 'insumo')
                                                        <input
                                                            type="number"
                                                            step="1"
                                                            min="1"
                                                            wire:model="presencialItems.{{ $idx }}.cantidad"
                                                            class="w-16 text-xs py-1 px-1.5 border-slate-300 rounded text-center"
                                                        />
                                                    @else
                                                        <span class="font-bold text-slate-700">1</span>
                                                    @endif
                                                </td>
                                                <td class="py-2 px-3 text-center">
                                                    <button
                                                        type="button"
                                                        wire:click="removerItemPresencial({{ $idx }})"
                                                        class="text-red-500 hover:text-red-700 font-bold p-1 rounded hover:bg-red-50 transition"
                                                    >
                                                        &times;
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @error('presencialItems') <p class="text-[11px] text-red-600 font-semibold">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Observaciones de Entrega (Opcional)</label>
                        <textarea
                            wire:model="presencialObservaciones"
                            rows="2"
                            placeholder="Ej: Material prestado para taller práctico..."
                            class="w-full text-xs border-slate-300 rounded-lg focus:ring-red-600 focus:border-red-600"
                        ></textarea>
                    </div>
                </div>

                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        wire:click="$set('mostrarModalPresencial', false)"
                        class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded-lg transition"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="confirmarPrestamoPresencial"
                        class="px-5 py-2 text-xs font-bold text-white bg-red-700 hover:bg-red-800 disabled:opacity-50 rounded-lg shadow-sm hover:shadow transition flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Confirmar y Despachar Materiales</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>