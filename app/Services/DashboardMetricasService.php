<?php

namespace App\Services;

use App\Models\Devolucion;
use App\Models\Entrega;
use App\Models\Falla;
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
