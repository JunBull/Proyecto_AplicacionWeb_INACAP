<?php

namespace App\Services;

use App\Exceptions\EquipoNoDisponibleException;
use App\Exceptions\OperacionDuplicadaException;
use App\Exceptions\StockInsuficienteException;
use App\Exceptions\StockReservadoClasesException;
use App\Models\DetalleSolicitud;
use App\Models\Devolucion;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\ParametroSistema;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PrestamoService
{
    /**
     * Consulta catálogo unificado de disponibilidad de insumos y equipos (CP-19).
     *
     * @return array{
     *     insumos: Collection<int, Insumo>,
     *     equipos: Collection<int, Equipo>
     * }
     */
    public function consultarDisponibilidad(?string $busqueda = null): array
    {
        $insumosQuery = Insumo::with('ubicacion');
        $equiposQuery = Equipo::with('ubicacion')->where('estado', 'disponible');

        if ($busqueda && trim($busqueda) !== '') {
            $term = '%'.trim($busqueda).'%';
            $insumosQuery->where(function ($q) use ($term) {
                $q->where('nombre', 'LIKE', $term)
                    ->orWhere('codigo_barras', 'LIKE', $term);
            });

            $equiposQuery->where(function ($q) use ($term) {
                $q->where('nombre', 'LIKE', $term)
                    ->orWhere('codigo_barras', 'LIKE', $term)
                    ->orWhere('marca', 'LIKE', $term)
                    ->orWhere('modelo', 'LIKE', $term);
            });
        }

        return [
            'insumos' => $insumosQuery->orderBy('nombre')->get(),
            'equipos' => $equiposQuery->orderBy('nombre')->get(),
        ];
    }

    /**
     * Registra una nueva solicitud de materiales anticipada (CP-19).
     *
     * @param array{
     *     fecha_requerida: string,
     *     observaciones?: ?string
     * } $datos
     * @param array<int, array{
     *     insumo_id?: ?int,
     *     equipo_id?: ?int,
     *     cantidad: float|int
     * }> $items
     */
    public function crearSolicitud(User $solicitante, array $datos, array $items): SolicitudMaterial
    {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Debe solicitar al menos un material o equipo.',
            ]);
        }

        if (empty($datos['fecha_requerida'])) {
            throw ValidationException::withMessages([
                'fecha_requerida' => 'La fecha requerida es obligatoria.',
            ]);
        }

        $fechaReq = Carbon::parse($datos['fecha_requerida'])->startOfDay();
        if ($fechaReq->lt(Carbon::today())) {
            throw ValidationException::withMessages([
                'fecha_requerida' => 'La fecha requerida no puede ser anterior a la fecha de hoy.',
            ]);
        }

        return DB::transaction(function () use ($solicitante, $datos, $items, $fechaReq) {
            $solicitud = SolicitudMaterial::create([
                'solicitante_id' => $solicitante->id,
                'revisada_por' => null,
                'asignatura_id' => ! empty($datos['asignatura_id']) ? (int) $datos['asignatura_id'] : null,
                'ubicacion_destino_id' => ! empty($datos['ubicacion_destino_id']) ? (int) $datos['ubicacion_destino_id'] : null,
                'fecha_solicitud' => now(),
                'fecha_requerida' => $fechaReq->toDateString(),
                'hora_requerida' => ! empty($datos['hora_requerida']) ? (string) $datos['hora_requerida'] : null,
                'estado' => 'pendiente',
                'observaciones' => isset($datos['observaciones']) ? trim($datos['observaciones']) : null,
            ]);

            foreach ($items as $index => $item) {
                $insumoId = ! empty($item['insumo_id']) ? (int) $item['insumo_id'] : null;
                $equipoId = ! empty($item['equipo_id']) ? (int) $item['equipo_id'] : null;
                $cantidad = (float) ($item['cantidad'] ?? 1);

                // Validación de restricción CHECK exclusiva: exactamente uno de insumo_id o equipo_id
                if (($insumoId === null && $equipoId === null) || ($insumoId !== null && $equipoId !== null)) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => 'Cada ítem debe referenciar exactamente a un insumo o a un equipo, nunca ambos o ninguno.',
                    ]);
                }

                if ($cantidad <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.cantidad" => 'La cantidad solicitada debe ser estrictamente mayor a 0.',
                    ]);
                }

                if ($equipoId !== null) {
                    $cantidad = 1.0;
                    if (! Equipo::where('id', $equipoId)->exists()) {
                        throw ValidationException::withMessages([
                            "items.{$index}.equipo_id" => "El equipo especificado (ID: {$equipoId}) no existe.",
                        ]);
                    }
                }

                if ($insumoId !== null) {
                    if (! Insumo::where('id', $insumoId)->exists()) {
                        throw ValidationException::withMessages([
                            "items.{$index}.insumo_id" => "El insumo especificado (ID: {$insumoId}) no existe.",
                        ]);
                    }
                }

                DetalleSolicitud::create([
                    'solicitud_id' => $solicitud->id,
                    'insumo_id' => $insumoId,
                    'equipo_id' => $equipoId,
                    'cantidad_solicitada' => $cantidad,
                    'cantidad_entregada' => 0,
                    'cantidad_devuelta' => 0,
                    'estado_devolucion' => 'no_aplica',
                ]);
            }

            return $solicitud->fresh(['detalles.insumo', 'detalles.equipo', 'solicitante']);
        });
    }

    /**
     * Aprueba una solicitud de materiales pendiente.
     */
    public function aprobarSolicitud(SolicitudMaterial $solicitud, User $revisor, ?string $observaciones = null): SolicitudMaterial
    {
        if ($solicitud->estado !== 'pendiente') {
            throw new InvalidArgumentException("Solo se pueden aprobar solicitudes en estado 'pendiente'.");
        }

        $solicitud->update([
            'estado' => 'aprobada',
            'revisada_por' => $revisor->id,
            'observaciones' => $observaciones ? trim($observaciones) : $solicitud->observaciones,
        ]);

        return $solicitud->fresh();
    }

    /**
     * Rechaza una solicitud de materiales pendiente.
     */
    public function rechazarSolicitud(SolicitudMaterial $solicitud, User $revisor, ?string $motivo = null): SolicitudMaterial
    {
        if (! in_array($solicitud->estado, ['pendiente', 'aprobada'], true)) {
            throw new InvalidArgumentException("No se puede rechazar una solicitud con estado actual '{$solicitud->estado}'.");
        }

        $solicitud->update([
            'estado' => 'rechazada',
            'revisada_por' => $revisor->id,
            'observaciones' => $motivo ? trim($motivo) : $solicitud->observaciones,
        ]);

        return $solicitud->fresh();
    }

    /**
     * Marca una solicitud como 'sin_disponibilidad' cuando no hay existencias físicas.
     */
    public function marcarSinDisponibilidad(SolicitudMaterial $solicitud, User $revisor, ?string $motivo = null): SolicitudMaterial
    {
        $solicitud->update([
            'estado' => 'sin_disponibilidad',
            'revisada_por' => $revisor->id,
            'observaciones' => $motivo ? trim($motivo) : $solicitud->observaciones,
        ]);

        return $solicitud->fresh();
    }

    /**
     * Confirma la entrega física de materiales en el mesón de pañol (CP-20, CP-22, PI-02, PI-03).
     * Aplica SELECT FOR UPDATE pesimista y actualización diferenciada (consumible vs retornable).
     *
     * @param  'docente'|'estudiante'  $receptorTipo
     * @param  array<int, int>|null  $detallesAEntregar  IDs de detalle_solicitud a despachar (si null, se entregan todos los pendientes)
     */
    public function entregarMateriales(
        SolicitudMaterial $solicitud,
        User $entregador,
        string $receptorNombre,
        string $receptorTipo,
        ?array $detallesAEntregar = null,
        ?string $receptorCredencialRut = null,
        bool $credencialVerificada = false,
        ?string $horaEntrega = null,
        ?string $horaDevolucionAcordada = null,
        bool $exigirValidacionCredencial = false
    ): Entrega {
        $nombreLimpio = trim($receptorNombre);
        if ($nombreLimpio === '') {
            throw ValidationException::withMessages([
                'receptor_nombre' => 'El nombre del receptor es obligatorio para garantizar la trazabilidad de la entrega.',
            ]);
        }

        if (! in_array($receptorTipo, ['docente', 'estudiante'], true)) {
            throw ValidationException::withMessages([
                'receptor_tipo' => 'El tipo de receptor debe ser exclusivamente "docente" o "estudiante".',
            ]);
        }

        if ($receptorTipo === 'estudiante' && $exigirValidacionCredencial) {
            $rutLimpio = trim((string) $receptorCredencialRut);
            if ($rutLimpio === '') {
                throw ValidationException::withMessages([
                    'receptor_credencial_rut' => 'El RUT o número de credencial institucional del estudiante es obligatorio.',
                ]);
            }

            if (! $credencialVerificada) {
                throw ValidationException::withMessages([
                    'credencial_verificada' => 'Debe validar y verificar físicamente la credencial del estudiante.',
                ]);
            }

            if (empty($horaDevolucionAcordada) || trim((string) $horaDevolucionAcordada) === '') {
                throw ValidationException::withMessages([
                    'hora_devolucion_acordada' => 'Debe registrar la hora acordada para la devolución del material.',
                ]);
            }
        }

        return DB::transaction(function () use (
            $solicitud,
            $entregador,
            $nombreLimpio,
            $receptorTipo,
            $detallesAEntregar,
            $receptorCredencialRut,
            $credencialVerificada,
            $horaEntrega,
            $horaDevolucionAcordada,
            $exigirValidacionCredencial
        ) {
            // Bloqueo pesimista de la cabecera de la solicitud
            /** @var SolicitudMaterial $solicitudBloqueada */
            $solicitudBloqueada = SolicitudMaterial::where('id', $solicitud->id)->lockForUpdate()->firstOrFail();

            // Detección de Idempotencia (CP-22): si la solicitud ya está cerrada o rechazada
            if ($solicitudBloqueada->estado === 'cerrada') {
                throw new OperacionDuplicadaException('Esta solicitud ya fue despachada y se encuentra cerrada (operación duplicada detectada).');
            }

            if (in_array($solicitudBloqueada->estado, ['rechazada', 'sin_disponibilidad'], true)) {
                throw new InvalidArgumentException("No se puede realizar entregas sobre una solicitud con estado '{$solicitudBloqueada->estado}'.");
            }

            // Consultar ítems con bloqueo pesimista
            $queryDetalles = DetalleSolicitud::where('solicitud_id', $solicitudBloqueada->id)->lockForUpdate();
            if ($detallesAEntregar !== null) {
                $queryDetalles->whereIn('id', $detallesAEntregar);
            }
            $detalles = $queryDetalles->get();

            // Filtrar detalles que aún tienen cantidad pendiente de entregar
            $detallesPendientes = $detalles->filter(function (DetalleSolicitud $det) {
                return (float) $det->cantidad_entregada < (float) $det->cantidad_solicitada;
            });

            if ($detallesPendientes->isEmpty()) {
                throw new OperacionDuplicadaException('Todos los ítems seleccionados ya han sido entregados con anterioridad (operación duplicada detectada).');
            }

            // Identificar si corresponde a un préstamo directo a estudiante (Sección 3.4)
            $esPrestamoEstudiante = ($receptorTipo === 'estudiante') && ($exigirValidacionCredencial || $solicitudBloqueada->solicitante?->rol !== 'docente');

            // Registro en la tabla 'entregas'
            $entrega = Entrega::create([
                'solicitud_id' => $solicitudBloqueada->id,
                'entregado_por' => $entregador->id,
                'receptor_nombre' => $nombreLimpio,
                'receptor_tipo' => $receptorTipo,
                'receptor_credencial_rut' => $receptorCredencialRut ? trim($receptorCredencialRut) : null,
                'credencial_verificada' => (bool) $credencialVerificada,
                'hora_entrega' => $horaEntrega ?: now()->format('H:i:s'),
                'hora_devolucion_acordada' => $horaDevolucionAcordada ?: null,
                'fecha_entrega' => now(),
            ]);

            foreach ($detallesPendientes as $detalle) {
                if ($detalle->equipo_id !== null) {
                    // Bloqueo pesimista del equipo (PI-02)
                    /** @var Equipo $equipo */
                    $equipo = Equipo::where('id', $detalle->equipo_id)->lockForUpdate()->firstOrFail();

                    if ($equipo->estado !== 'disponible') {
                        throw new EquipoNoDisponibleException(
                            "El equipo '{$equipo->nombre}' (código: {$equipo->codigo_barras}) ya no se encuentra disponible (estado actual: {$equipo->estado})."
                        );
                    }

                    // Validación de reserva de clases para préstamos a estudiantes (CP-25, Sección 3.4)
                    if ($esPrestamoEstudiante) {
                        if ($equipo->reservado_clases) {
                            throw new StockReservadoClasesException(
                                "El equipo '{$equipo->nombre}' (código: {$equipo->codigo_barras}) está marcado como reservado exclusivamente para clases docentes."
                            );
                        }

                        $stockMinimoClasesDefault = (int) ParametroSistema::get('stock_minimo_clases_default', 1);
                        $equiposDisponiblesMismoTipo = Equipo::where('nombre', $equipo->nombre)
                            ->where('estado', 'disponible')
                            ->lockForUpdate()
                            ->get(['id'])
                            ->count();

                        if (($equiposDisponiblesMismoTipo - 1) < $stockMinimoClasesDefault && $equiposDisponiblesMismoTipo <= $stockMinimoClasesDefault) {
                            throw new StockReservadoClasesException(
                                "Stock reservado para clases: Quedan {$equiposDisponiblesMismoTipo} unidad(es) del equipo '{$equipo->nombre}', reservadas para el dictado de talleres docentes (umbral mínimo: {$stockMinimoClasesDefault}). Operación rechazada."
                            );
                        }
                    }

                    // Actualizar estado del activo a prestado
                    $equipo->update(['estado' => 'prestado']);

                    $detalle->cantidad_entregada = 1;
                    $detalle->estado_devolucion = 'pendiente';
                    $detalle->save();
                } elseif ($detalle->insumo_id !== null) {
                    // Bloqueo pesimista del insumo (PI-03)
                    /** @var Insumo $insumo */
                    $insumo = Insumo::where('id', $detalle->insumo_id)->lockForUpdate()->firstOrFail();

                    $cantidadADespachar = (float) $detalle->cantidad_solicitada - (float) $detalle->cantidad_entregada;
                    $stockDisponible = (float) $insumo->stock_actual;

                    if ($cantidadADespachar > $stockDisponible) {
                        throw new StockInsuficienteException(
                            "Stock insuficiente para el insumo '{$insumo->nombre}'. Existencias físicas: {$stockDisponible}, cantidad requerida: {$cantidadADespachar}."
                        );
                    }

                    // Validación de reserva de clases para insumos retornables a estudiantes (CP-25, Sección 3.4)
                    if ($esPrestamoEstudiante && $insumo->es_retornable) {
                        $stockMinimoClases = (float) ($insumo->stock_minimo_clases ?? 2);
                        if (($stockDisponible - $stockMinimoClases) < $cantidadADespachar) {
                            throw new StockReservadoClasesException(
                                "Stock reservado para clases: El insumo retornable '{$insumo->nombre}' cuenta con {$stockDisponible} unidades, de las cuales {$stockMinimoClases} están reservadas para docencia. No es posible prestar {$cantidadADespachar} unidad(es) a estudiantes."
                            );
                        }
                    }

                    // Rebaja directa de stock en inventario
                    $insumo->stock_actual = $stockDisponible - $cantidadADespachar;
                    $insumo->save();

                    if ($insumo->es_retornable) {
                        // Insumo retornable: se registra salida por préstamo y se mantiene pendiente de devolución
                        MovimientoInventario::create([
                            'insumo_id' => $insumo->id,
                            'usuario_id' => $entregador->id,
                            'solicitud_id' => $solicitudBloqueada->id,
                            'tipo' => 'salida_prestamo',
                            'cantidad' => $cantidadADespachar,
                            'fecha' => now(),
                        ]);

                        $detalle->cantidad_entregada = (float) $detalle->cantidad_entregada + $cantidadADespachar;
                        $detalle->estado_devolucion = 'pendiente';
                    } else {
                        // Insumo consumible: salida definitiva por consumo de taller
                        MovimientoInventario::create([
                            'insumo_id' => $insumo->id,
                            'usuario_id' => $entregador->id,
                            'solicitud_id' => $solicitudBloqueada->id,
                            'tipo' => 'salida_consumo',
                            'cantidad' => $cantidadADespachar,
                            'fecha' => now(),
                        ]);

                        $detalle->cantidad_entregada = (float) $detalle->cantidad_entregada + $cantidadADespachar;
                        $detalle->estado_devolucion = 'no_aplica';
                    }

                    $detalle->save();
                }
            }

            // Evaluar estado consolidado de la solicitud (BPMN Proceso 3)
            $todosLosDetalles = DetalleSolicitud::where('solicitud_id', $solicitudBloqueada->id)->get();
            $tienePendientesDevolucion = $todosLosDetalles->contains(fn ($det) => $det->estado_devolucion === 'pendiente');

            if ($tienePendientesDevolucion) {
                $solicitudBloqueada->update(['estado' => 'entregada_abierta']);
            } else {
                $solicitudBloqueada->update(['estado' => 'cerrada']);
            }

            return $entrega->fresh(['solicitud.detalles.insumo', 'solicitud.detalles.equipo', 'entregador']);
        });
    }

    /**
     * Registra un préstamo presencial directo en ventanilla a un estudiante (flujo sin solicitud previa).
     * Ejecuta de forma atómica la creación de la cabecera, detalles y entrega física con validación
     * obligatoria de credencial física y piso mínimo de clases (lockForUpdate).
     *
     * @param array{
     *     nombre: string,
     *     rut: string,
     *     credencial_verificada: bool,
     *     hora_devolucion_acordada: string,
     *     hora_entrega?: ?string,
     *     ubicacion_destino_id?: ?int,
     *     asignatura_id?: ?int,
     *     observaciones?: ?string
     * } $datosEstudiante
     * @param array<int, array{
     *     tipo: 'equipo'|'insumo',
     *     id: int,
     *     cantidad?: float|int
     * }> $items
     */
    public function registrarPrestamoPresencialEnMeson(
        User $panolero,
        array $datosEstudiante,
        array $items
    ): Entrega {
        $nombre = trim($datosEstudiante['nombre'] ?? '');
        if ($nombre === '') {
            throw ValidationException::withMessages([
                'receptor_nombre' => 'El nombre completo del estudiante es obligatorio.',
            ]);
        }

        $rut = trim($datosEstudiante['rut'] ?? '');
        if ($rut === '') {
            throw ValidationException::withMessages([
                'receptor_credencial_rut' => 'El RUT o credencial institucional del estudiante es obligatorio.',
            ]);
        }

        $credencialVerificada = (bool) ($datosEstudiante['credencial_verificada'] ?? false);
        if (! $credencialVerificada) {
            throw ValidationException::withMessages([
                'credencial_verificada' => 'Debe validar y verificar físicamente la credencial del estudiante.',
            ]);
        }

        $horaDevolucionAcordada = trim($datosEstudiante['hora_devolucion_acordada'] ?? '');
        if ($horaDevolucionAcordada === '') {
            throw ValidationException::withMessages([
                'hora_devolucion_acordada' => 'Debe registrar la hora acordada para la devolución del material.',
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Debe seleccionar al menos un equipo o insumo para entregar.',
            ]);
        }

        return DB::transaction(function () use ($panolero, $datosEstudiante, $items, $nombre, $rut, $horaDevolucionAcordada) {
            $ubicacion = ! empty($datosEstudiante['ubicacion_destino_id'])
                ? Ubicacion::find($datosEstudiante['ubicacion_destino_id'])
                : (Ubicacion::where('tipo', 'laboratorio')->first() ?? Ubicacion::first());
            $ubicacionDestinoId = $ubicacion?->id;

            $horaEntrega = ! empty($datosEstudiante['hora_entrega'])
                ? trim($datosEstudiante['hora_entrega'])
                : now()->format('H:i:s');

            $observaciones = ! empty($datosEstudiante['observaciones'])
                ? trim($datosEstudiante['observaciones'])
                : "Préstamo presencial en mesón a estudiante: {$nombre} (RUT: {$rut})";

            // 1. Crear cabecera de solicitud aprobada en el acto
            $solicitud = SolicitudMaterial::create([
                'solicitante_id' => $panolero->id,
                'estado' => 'aprobada',
                'fecha_solicitud' => now(),
                'fecha_requerida' => now()->toDateString(),
                'hora_requerida' => $horaEntrega,
                'ubicacion_destino_id' => $ubicacionDestinoId,
                'asignatura_id' => ! empty($datosEstudiante['asignatura_id']) ? (int) $datosEstudiante['asignatura_id'] : null,
                'revisada_por' => $panolero->id,
                'observaciones' => $observaciones,
            ]);

            // 2. Crear detalles de solicitud
            foreach ($items as $item) {
                $tipo = $item['tipo'] ?? null;
                $id = (int) ($item['id'] ?? 0);
                $cantidad = isset($item['cantidad']) ? max(1.0, (float) $item['cantidad']) : 1.0;

                DetalleSolicitud::create([
                    'solicitud_id' => $solicitud->id,
                    'equipo_id' => $tipo === 'equipo' ? $id : null,
                    'insumo_id' => $tipo === 'insumo' ? $id : null,
                    'cantidad_solicitada' => $cantidad,
                    'cantidad_entregada' => 0.0,
                    'cantidad_devuelta' => 0.0,
                    'estado_devolucion' => 'pendiente',
                ]);
            }

            // 3. Ejecutar entrega física atómica con validación de reservas y bloqueo pesimista
            return $this->entregarMateriales(
                solicitud: $solicitud,
                entregador: $panolero,
                receptorNombre: $nombre,
                receptorTipo: 'estudiante',
                detallesAEntregar: null,
                receptorCredencialRut: $rut,
                credencialVerificada: true,
                horaEntrega: $horaEntrega,
                horaDevolucionAcordada: $horaDevolucionAcordada,
                exigirValidacionCredencial: true
            );
        });
    }

    /**
     * Registra un préstamo a un estudiante en mesón de pañol con verificación obligatoria
     * de credencial física y hora límite de devolución acordada (CP-26, Sección 3.4).
     */
    public function registrarPrestamoEstudiante(
        SolicitudMaterial $solicitud,
        User $entregador,
        string $estudianteNombre,
        string $credencialRut,
        bool $credencialVerificada,
        string $horaEntrega,
        string $horaDevolucionAcordada,
        ?array $detallesAEntregar = null
    ): Entrega {
        return $this->entregarMateriales(
            solicitud: $solicitud,
            entregador: $entregador,
            receptorNombre: $estudianteNombre,
            receptorTipo: 'estudiante',
            detallesAEntregar: $detallesAEntregar,
            receptorCredencialRut: $credencialRut,
            credencialVerificada: $credencialVerificada,
            horaEntrega: $horaEntrega,
            horaDevolucionAcordada: $horaDevolucionAcordada,
            exigirValidacionCredencial: true
        );
    }

    /**
     * Registra una devolución total, parcial o con daños físicos (CP-21, CP-22).
     *
     * @param array<int, array{
     *     detalle_id: int,
     *     cantidad?: float|int,
     *     conforme: bool,
     *     descripcion_dano?: ?string
     * }> $itemsDevolucion
     */
    public function registrarDevolucion(
        Entrega $entrega,
        User $recibidor,
        array $itemsDevolucion,
        ?string $observacionesGenerales = null
    ): Devolucion {
        if (empty($itemsDevolucion)) {
            throw ValidationException::withMessages([
                'items' => 'Debe registrar al menos un ítem devuelto.',
            ]);
        }

        return DB::transaction(function () use ($entrega, $recibidor, $itemsDevolucion, $observacionesGenerales) {
            /** @var Entrega $entregaBloqueada */
            $entregaBloqueada = Entrega::where('id', $entrega->id)->lockForUpdate()->firstOrFail();

            /** @var SolicitudMaterial $solicitudBloqueada */
            $solicitudBloqueada = SolicitudMaterial::where('id', $entregaBloqueada->solicitud_id)->lockForUpdate()->firstOrFail();

            if ($solicitudBloqueada->estado === 'cerrada') {
                throw new OperacionDuplicadaException('El préstamo ya se encuentra cerrado en su totalidad; no existen unidades pendientes.');
            }

            $algunaFallaRegistrada = false;
            $itemsProcesados = 0;

            foreach ($itemsDevolucion as $item) {
                $detalleId = (int) $item['detalle_id'];
                /** @var DetalleSolicitud $detalle */
                $detalle = DetalleSolicitud::where('id', $detalleId)
                    ->where('solicitud_id', $solicitudBloqueada->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $esConforme = (bool) ($item['conforme'] ?? true);
                if (! $esConforme) {
                    $algunaFallaRegistrada = true;
                }

                if ($detalle->equipo_id !== null) {
                    /** @var Equipo $equipo */
                    $equipo = Equipo::where('id', $detalle->equipo_id)->lockForUpdate()->firstOrFail();

                    // Idempotencia: no permitir devolver un equipo que ya fue devuelto
                    if ((float) $detalle->cantidad_devuelta >= 1.0) {
                        continue;
                    }

                    $detalle->cantidad_devuelta = 1.0;

                    if ($esConforme) {
                        $equipo->update(['estado' => 'disponible']);
                        $detalle->estado_devolucion = 'completa';
                    } else {
                        // Con daño: pasa a no disponible y se vincula al módulo de fallas/revisión técnica (CP-21, BPMN Proceso 3 y 4)
                        $equipo->update(['estado' => 'no_disponible']);
                        $detalle->estado_devolucion = 'con_danos';

                        $descripcionDano = ! empty($item['descripcion_dano'])
                            ? trim($item['descripcion_dano'])
                            : ($observacionesGenerales ? trim($observacionesGenerales) : 'Equipo devuelto con daños físicos en mesón de pañol.');

                        Falla::create([
                            'equipo_id' => $equipo->id,
                            'reportada_por' => $recibidor->id,
                            'atendida_por' => null,
                            'descripcion' => $descripcionDano,
                            'fecha_reporte' => now(),
                            'estado' => 'reportada',
                        ]);
                    }

                    $detalle->save();
                    $itemsProcesados++;
                } elseif ($detalle->insumo_id !== null) {
                    /** @var Insumo $insumo */
                    $insumo = Insumo::where('id', $detalle->insumo_id)->lockForUpdate()->firstOrFail();

                    if (! $insumo->es_retornable) {
                        continue;
                    }

                    $cantidadDev = (float) ($item['cantidad'] ?? 0);
                    $pendiente = (float) $detalle->cantidad_entregada - (float) $detalle->cantidad_devuelta;

                    if ($cantidadDev <= 0) {
                        continue;
                    }

                    if ($cantidadDev > $pendiente) {
                        throw new InvalidArgumentException(
                            "La cantidad devuelta ({$cantidadDev}) excede la cantidad pendiente por retornar ({$pendiente})."
                        );
                    }

                    $detalle->cantidad_devuelta = (float) $detalle->cantidad_devuelta + $cantidadDev;

                    if ($esConforme) {
                        // En buen estado: reingreso a stock disponible
                        $insumo->stock_actual = (float) $insumo->stock_actual + $cantidadDev;
                        $insumo->save();

                        MovimientoInventario::create([
                            'insumo_id' => $insumo->id,
                            'usuario_id' => $recibidor->id,
                            'solicitud_id' => $solicitudBloqueada->id,
                            'tipo' => 'reingreso_devolucion',
                            'cantidad' => $cantidadDev,
                            'fecha' => now(),
                        ]);

                        if ((float) $detalle->cantidad_devuelta >= (float) $detalle->cantidad_entregada) {
                            $detalle->estado_devolucion = 'completa';
                        } else {
                            $detalle->estado_devolucion = 'parcial';
                        }
                    } else {
                        // Con daño: las unidades dañadas NO se reincorporan a stock disponible
                        $detalle->estado_devolucion = 'con_danos';
                    }

                    $detalle->save();
                    $itemsProcesados++;
                }
            }

            if ($itemsProcesados === 0) {
                throw new OperacionDuplicadaException('No se procesó ningún ítem pendiente. Los ítems seleccionados ya fueron devueltos previamente.');
            }

            // Registrar la devolución
            $devolucion = Devolucion::create([
                'entrega_id' => $entregaBloqueada->id,
                'recibido_por' => $recibidor->id,
                'fecha_devolucion' => now(),
                'conforme' => ! $algunaFallaRegistrada,
                'observaciones' => $observacionesGenerales ? trim($observacionesGenerales) : null,
            ]);

            // Verificar si aún quedan ítems retornables pendientes en toda la solicitud (BPMN Paso 15)
            $detallesActualizados = DetalleSolicitud::where('solicitud_id', $solicitudBloqueada->id)->get();
            $quedanPendientes = false;

            foreach ($detallesActualizados as $det) {
                if ($det->equipo_id !== null && (float) $det->cantidad_devuelta < 1.0) {
                    $quedanPendientes = true;
                    break;
                }
                if ($det->insumo_id !== null && $det->insumo?->es_retornable && (float) $det->cantidad_devuelta < (float) $det->cantidad_entregada) {
                    $quedanPendientes = true;
                    break;
                }
            }

            if ($quedanPendientes) {
                $solicitudBloqueada->update(['estado' => 'entregada_abierta']);
            } else {
                $solicitudBloqueada->update(['estado' => 'cerrada']);
            }

            return $devolucion->fresh(['entrega.solicitud', 'recibidor']);
        });
    }
}
