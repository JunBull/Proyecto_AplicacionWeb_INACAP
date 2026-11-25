<?php

namespace App\Services;

use App\Models\BajaReemplazo;
use App\Models\Compra;
use App\Models\DetalleSolicitud;
use App\Models\Devolucion;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Mantenimiento;
use App\Models\Presupuesto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetricasService
{
    /**
     * Calcula la tasa de cumplimiento a tiempo en devoluciones de estudiantes.
     *
     * @return array{
     *     total_devoluciones_estudiantes: int,
     *     a_tiempo: int,
     *     con_retraso: int,
     *     porcentaje_a_tiempo: float,
     *     retrasos_activos: int
     * }
     */
    public function obtenerMetricasPuntualidadEstudiantes(?string $periodo = '30_dias'): array
    {
        $fechaDesde = $this->resolverFechaDesde($periodo);

        $devolucionesQuery = Devolucion::with('entrega')
            ->whereHas('entrega', function ($q) use ($fechaDesde) {
                $q->where('receptor_tipo', 'estudiante');
                if ($fechaDesde) {
                    $q->where('fecha_entrega', '>=', $fechaDesde);
                }
            });

        $devoluciones = $devolucionesQuery->get();
        $totalDevoluciones = $devoluciones->count();
        $aTiempo = 0;
        $conRetraso = 0;

        foreach ($devoluciones as $dev) {
            $entrega = $dev->entrega;
            if (! $entrega || ! $entrega->fecha_hora_acordada) {
                $aTiempo++;

                continue;
            }

            if ($dev->fecha_devolucion->lessThanOrEqualTo($entrega->fecha_hora_acordada)) {
                $aTiempo++;
            } else {
                $conRetraso++;
            }
        }

        $porcentaje = $totalDevoluciones > 0
            ? round(($aTiempo / $totalDevoluciones) * 100, 1)
            : 100.0;

        // Préstamos a estudiantes abiertos que ya superaron la hora acordada
        $retrasosActivos = Entrega::where('receptor_tipo', 'estudiante')
            ->whereHas('solicitud', fn ($q) => $q->where('estado', 'entregada_abierta'))
            ->whereNotNull('hora_devolucion_acordada')
            ->where(function ($q) {
                $hoy = now()->toDateString();
                $horaActual = now()->format('H:i:s');
                $q->whereDate('fecha_entrega', '<', $hoy)
                    ->orWhere(function ($q2) use ($hoy, $horaActual) {
                        $q2->whereDate('fecha_entrega', $hoy)
                            ->where('hora_devolucion_acordada', '<', $horaActual);
                    });
            })
            ->count();

        return [
            'total_devoluciones_estudiantes' => $totalDevoluciones,
            'a_tiempo' => $aTiempo,
            'con_retraso' => $conRetraso,
            'porcentaje_a_tiempo' => $porcentaje,
            'retrasos_activos' => $retrasosActivos,
        ];
    }

    /**
     * Obtiene los equipos con mayor número de fallas técnicas reportadas.
     */
    public function obtenerTopEquiposConFallas(int $limite = 5, ?string $periodo = '30_dias'): Collection
    {
        $fechaDesde = $this->resolverFechaDesde($periodo);

        $query = Falla::select('equipo_id', DB::raw('count(*) as total_fallas'))
            ->whereNotNull('equipo_id')
            ->with(['equipo' => function ($q) {
                $q->select('id', 'nombre', 'marca', 'modelo', 'codigo_barras', 'estado');
            }])
            ->groupBy('equipo_id');

        if ($fechaDesde) {
            $query->where('fecha_reporte', '>=', $fechaDesde);
        }

        return $query->orderByDesc('total_fallas')
            ->take($limite)
            ->get();
    }

    /**
     * Obtiene los materiales más solicitados y despachados (Top Equipos y Top Insumos).
     *
     * @return array{
     *     equipos: Collection,
     *     insumos: Collection
     * }
     */
    public function obtenerTopMateriales(int $limite = 5, ?string $periodo = '30_dias'): array
    {
        $fechaDesde = $this->resolverFechaDesde($periodo);

        // Top Equipos solicitados
        $equiposQuery = DB::table('detalle_solicitud')
            ->join('equipos', 'detalle_solicitud.equipo_id', '=', 'equipos.id')
            ->select('equipos.nombre', 'equipos.marca', 'equipos.modelo', DB::raw('count(detalle_solicitud.id) as veces_pedido'))
            ->whereNotNull('detalle_solicitud.equipo_id')
            ->groupBy('equipos.nombre', 'equipos.marca', 'equipos.modelo');

        if ($fechaDesde) {
            $equiposQuery->where('detalle_solicitud.created_at', '>=', $fechaDesde);
        }

        $topEquipos = $equiposQuery->orderByDesc('veces_pedido')->take($limite)->get();

        // Top Insumos entregados/consumidos
        $insumosQuery = DB::table('detalle_solicitud')
            ->join('insumos', 'detalle_solicitud.insumo_id', '=', 'insumos.id')
            ->select('insumos.nombre', 'insumos.unidad', DB::raw('sum(detalle_solicitud.cantidad_entregada) as total_entregado'))
            ->whereNotNull('detalle_solicitud.insumo_id')
            ->where('detalle_solicitud.cantidad_entregada', '>', 0)
            ->groupBy('insumos.nombre', 'insumos.unidad');

        if ($fechaDesde) {
            $insumosQuery->where('detalle_solicitud.created_at', '>=', $fechaDesde);
        }

        $topInsumos = $insumosQuery->orderByDesc('total_entregado')->take($limite)->get();

        return [
            'equipos' => $topEquipos,
            'insumos' => $topInsumos,
        ];
    }

    /**
     * Calcula la distribución de atenciones por hora del día (08:00 a 21:00 hrs).
     *
     * @return array{
     *     horas: array<int, array{hora: string, hora_num: int, total: int, porcentaje: float, es_punta: bool}>,
     *     maximo: int,
     *     total_entregas: int
     * }
     */
    public function obtenerDistribucionHoraria(?string $periodo = '30_dias'): array
    {
        $fechaDesde = $this->resolverFechaDesde($periodo);

        $driver = DB::getDriverName();
        $horaExp = $driver === 'pgsql'
            ? 'EXTRACT(HOUR FROM COALESCE(hora_entrega, fecha_entrega::time))::integer'
            : "CAST(strftime('%H', COALESCE(hora_entrega, fecha_entrega)) AS INTEGER)";

        $query = DB::table('entregas')
            ->select(DB::raw("{$horaExp} as hora"), DB::raw('count(*) as total'))
            ->whereNotNull('fecha_entrega');

        if ($fechaDesde) {
            $query->where('fecha_entrega', '>=', $fechaDesde);
        }

        $filas = $query->groupBy(DB::raw($horaExp))->pluck('total', 'hora')->all();

        $maximo = 0;
        $totalEntregas = 0;
        foreach ($filas as $cant) {
            $totalEntregas += (int) $cant;
        }

        for ($h = 8; $h <= 21; $h++) {
            $cant = (int) ($filas[$h] ?? 0);
            if ($cant > $maximo) {
                $maximo = $cant;
            }
        }

        $maximoNormalizado = max(1, $maximo);
        $distribucion = [];

        for ($h = 8; $h <= 21; $h++) {
            $cant = (int) ($filas[$h] ?? 0);
            $porcentaje = round(($cant / $maximoNormalizado) * 100, 1);
            $distribucion[] = [
                'hora' => sprintf('%02d:00', $h),
                'hora_num' => $h,
                'total' => $cant,
                'porcentaje' => $porcentaje,
                'es_punta' => ($cant > 0 && $cant >= ($maximo * 0.8)),
            ];
        }

        return [
            'horas' => $distribucion,
            'maximo' => $maximo,
            'total_entregas' => $totalEntregas,
        ];
    }

    /**
     * Obtiene contadores generales de actividad en pañol para el período indicado.
     *
     * @return array{
     *     total_prestamos: int,
     *     total_fallas: int,
     *     total_insumos_consumidos: float
     * }
     */
    public function obtenerResumenGeneral(?string $periodo = '30_dias'): array
    {
        $fechaDesde = $this->resolverFechaDesde($periodo);

        $totalPrestamos = Entrega::when($fechaDesde, function ($q) use ($fechaDesde) {
            $q->where('fecha_entrega', '>=', $fechaDesde);
        })->count();

        $totalFallas = Falla::when($fechaDesde, function ($q) use ($fechaDesde) {
            $q->where('fecha_reporte', '>=', $fechaDesde);
        })->count();

        $totalInsumosConsumidos = (float) DetalleSolicitud::whereNotNull('insumo_id')
            ->when($fechaDesde, function ($q) use ($fechaDesde) {
                $q->where('created_at', '>=', $fechaDesde);
            })
            ->sum('cantidad_entregada');

        return [
            'total_prestamos' => $totalPrestamos,
            'total_fallas' => $totalFallas,
            'total_insumos_consumidos' => $totalInsumosConsumidos,
        ];
    }

    /**
     * Obtiene resumen de mantenimientos, bajas/reemplazos y estado actual de flota de equipos.
     *
     * @return array{
     *     mantenimientos_preventivos: int,
     *     mantenimientos_correctivos: int,
     *     mantenimientos_realizados: int,
     *     mantenimientos_pendientes: int,
     *     bajas_propuestas: int,
     *     bajas_aprobadas: int,
     *     equipos_activos: int,
     *     equipos_en_mantenimiento: int
     * }
     */
    public function obtenerResumenMantenimientosYBajas(?string $periodo = '30_dias'): array
    {
        $fechaDesde = $this->resolverFechaDesde($periodo);

        $mantenimientosBase = Mantenimiento::when($fechaDesde, function ($q) use ($fechaDesde) {
            $q->where('fecha_programada', '>=', $fechaDesde->toDateString());
        });

        $bajasBase = BajaReemplazo::when($fechaDesde, function ($q) use ($fechaDesde) {
            $q->where('fecha', '>=', $fechaDesde->toDateString());
        });

        return [
            'mantenimientos_preventivos' => (clone $mantenimientosBase)->where('tipo', 'preventivo')->count(),
            'mantenimientos_correctivos' => (clone $mantenimientosBase)->where('tipo', 'correctivo')->count(),
            'mantenimientos_realizados' => (clone $mantenimientosBase)->where('estado', 'realizado')->count(),
            'mantenimientos_pendientes' => (clone $mantenimientosBase)->whereIn('estado', ['programado', 'en_proceso', 'vencido'])->count(),
            'bajas_propuestas' => (clone $bajasBase)->where('estado', 'propuesta')->count(),
            'bajas_aprobadas' => (clone $bajasBase)->where('estado', 'aprobada')->count(),
            'equipos_activos' => Equipo::whereIn('estado', ['disponible', 'prestado'])->count(),
            'equipos_en_mantenimiento' => Equipo::where('estado', 'en_mantenimiento')->count(),
        ];
    }

    /**
     * Obtiene ejecución presupuestaria por carrera para el año vigente y compras registradas en el período.
     *
     * @return array{
     *     anio: int,
     *     total_asignado: float,
     *     total_saldo: float,
     *     total_ejecutado: float,
     *     porcentaje_ejecutado: float,
     *     total_compras_periodo: int,
     *     monto_compras_periodo: float,
     *     carreras: Collection
     * }
     */
    public function obtenerMetricasPresupuestosYCompras(?string $periodo = '30_dias', ?int $anio = null): array
    {
        $anioVigente = $anio ?? (int) now()->year;
        $fechaDesde = $this->resolverFechaDesde($periodo);

        $presupuestos = Presupuesto::with('carrera:id,codigo,nombre,activa')
            ->where('anio', $anioVigente)
            ->orderByDesc('monto_asignado')
            ->get()
            ->map(function (Presupuesto $presupuesto): array {
                $asignado = (float) $presupuesto->monto_asignado;
                $saldo = (float) $presupuesto->saldo_actual;
                $ejecutado = max(0.0, $asignado - $saldo);
                $porcentaje = $asignado > 0 ? round(($ejecutado / $asignado) * 100, 1) : 0.0;

                return [
                    'carrera_nombre' => $presupuesto->carrera?->nombre ?? 'Carrera sin nombre',
                    'carrera_codigo' => $presupuesto->carrera?->codigo ?? '-',
                    'monto_asignado' => $asignado,
                    'saldo_actual' => $saldo,
                    'monto_ejecutado' => $ejecutado,
                    'porcentaje_ejecutado' => min(100.0, $porcentaje),
                ];
            });

        $totalAsignado = (float) $presupuestos->sum('monto_asignado');
        $totalSaldo = (float) $presupuestos->sum('saldo_actual');
        $totalEjecutado = max(0.0, $totalAsignado - $totalSaldo);
        $porcentajeGlobal = $totalAsignado > 0 ? round(($totalEjecutado / $totalAsignado) * 100, 1) : 0.0;

        $comprasQuery = Compra::where('estado', '!=', 'anulada')
            ->when($fechaDesde, function ($q) use ($fechaDesde) {
                $q->where('fecha', '>=', $fechaDesde->toDateString());
            });

        $totalComprasPeriodo = (clone $comprasQuery)->count();
        $montoComprasPeriodo = (float) (clone $comprasQuery)->sum('monto_total');

        return [
            'anio' => $anioVigente,
            'total_asignado' => $totalAsignado,
            'total_saldo' => $totalSaldo,
            'total_ejecutado' => $totalEjecutado,
            'porcentaje_ejecutado' => min(100.0, $porcentajeGlobal),
            'total_compras_periodo' => $totalComprasPeriodo,
            'monto_compras_periodo' => $montoComprasPeriodo,
            'carreras' => $presupuestos,
        ];
    }

    private function resolverFechaDesde(?string $periodo): ?Carbon
    {
        return match ($periodo) {
            '30_dias' => now()->subDays(30),
            'semestre' => now()->subMonths(6),
            'anio' => now()->subYear(),
            default => null,
        };
    }
}
