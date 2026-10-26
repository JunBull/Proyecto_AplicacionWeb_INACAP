<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Proveedor;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MantenimientoService
{
    /**
     * Días de anticipación para alertas preventivas según criticidad del activo (CP-05).
     */
    public const DIAS_ANTICIPACION_CRITICIDAD = [
        'alta' => 30,
        'media' => 15,
        'baja' => 7,
    ];

    /**
     * Programa un mantenimiento preventivo periódico o correctivo (CP-05).
     *
     * @param 'preventivo'|'correctivo' $tipo
     * @param 'semestral'|'anual'|'no_aplica'|null $frecuencia
     */
    public function programarMantenimiento(
        Equipo $equipo,
        string $tipo,
        ?string $frecuencia,
        CarbonInterface|string $fechaProgramada,
        ?int $proveedorId = null,
        ?string $observaciones = null
    ): Mantenimiento {
        $fecha = is_string($fechaProgramada) ? Carbon::parse($fechaProgramada) : $fechaProgramada;

        if ($tipo === 'preventivo' && ! in_array($frecuencia, ['semestral', 'anual'], true)) {
            throw ValidationException::withMessages([
                'frecuencia' => 'Los mantenimientos preventivos requieren periodicidad semestral o anual.',
            ]);
        }

        if ($proveedorId && ! Proveedor::where('id', $proveedorId)->exists()) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'El proveedor especificado no existe.',
            ]);
        }

        return Mantenimiento::create([
            'equipo_id' => $equipo->id,
            'proveedor_id' => $proveedorId,
            'tipo' => $tipo,
            'frecuencia' => $frecuencia ?? 'no_aplica',
            'fecha_programada' => $fecha->toDateString(),
            'fecha_realizada' => null,
            'estado' => 'programado',
            'observaciones' => $observaciones ? trim($observaciones) : null,
        ]);
    }

    /**
     * Registra la ejecución de un mantenimiento técnico, adjuntando informe si corresponde.
     */
    public function registrarEjecucion(
        Mantenimiento $mantenimiento,
        CarbonInterface|string $fechaRealizada,
        ?string $observaciones = null,
        ?UploadedFile $informe = null,
        ?User $subidoPor = null
    ): Mantenimiento {
        $fecha = is_string($fechaRealizada) ? Carbon::parse($fechaRealizada) : $fechaRealizada;

        return DB::transaction(function () use ($mantenimiento, $fecha, $observaciones, $informe, $subidoPor) {
            $mantenimiento->update([
                'fecha_realizada' => $fecha->toDateString(),
                'estado' => 'realizado',
                'observaciones' => $observaciones ? trim($observaciones) : $mantenimiento->observaciones,
            ]);

            // Si se adjunta un informe técnico, se respalda en documentos
            if ($informe instanceof UploadedFile) {
                $ruta = $informe->store('mantenimientos/informes', 'public');
                $subidorId = $subidoPor?->id ?? User::where('rol', 'encargado_panol')->value('id') ?? User::first()?->id ?? 1;

                Documento::create([
                    'tipo' => 'informe_tecnico',
                    'ruta' => $ruta,
                    'nombre_original' => $informe->getClientOriginalName(),
                    'hash_sha256' => hash_file('sha256', $informe->getRealPath() ?: $informe->path()),
                    'documentable_type' => Mantenimiento::class,
                    'documentable_id' => $mantenimiento->id,
                    'subido_por' => $subidorId,
                    'creado_en' => now(),
                ]);
            }

            // Declarar equipo operativo y disponible si no tiene otras fallas activas
            $equipo = $mantenimiento->equipo;
            if ($equipo && $equipo->estado === 'en_mantenimiento') {
                $tieneOtrasFallas = $equipo->fallas()->whereIn('estado', ['reportada', 'en_revision', 'en_reparacion'])->exists();
                if (! $tieneOtrasFallas) {
                    $equipo->update(['estado' => 'disponible']);
                }
            }

            return $mantenimiento->fresh(['equipo', 'documentos', 'proveedor']);
        });
    }

    /**
     * Evalúa los mantenimientos programados y genera alertas preventivas según nivel de criticidad (CP-05).
     *
     * @return array<int, array{
     *     mantenimiento_id: int,
     *     equipo_id: int,
     *     equipo_nombre: string,
     *     criticidad: string,
     *     fecha_programada: string,
     *     dias_restantes: int,
     *     tipo_alerta: 'preventiva'|'vencida'
     * }>
     */
    public function evaluarAlertasMantenimiento(?CarbonInterface $fechaReferencia = null): array
    {
        $hoy = $fechaReferencia ? Carbon::parse($fechaReferencia)->startOfDay() : now()->startOfDay();
        $alertas = [];

        $mantenimientos = Mantenimiento::with('equipo')
            ->whereIn('estado', ['programado', 'en_proceso', 'vencido'])
            ->get();

        foreach ($mantenimientos as $m) {
            $equipo = $m->equipo;
            if (! $equipo) {
                continue;
            }

            $fechaProg = Carbon::parse($m->fecha_programada)->startOfDay();
            $diasRestantes = (int) $hoy->diffInDays($fechaProg, false);
            $criticidad = $equipo->criticidad ?? 'media';
            $diasUmbral = self::DIAS_ANTICIPACION_CRITICIDAD[$criticidad] ?? 15;

            if ($diasRestantes < 0) {
                // Ya venció
                if ($m->estado !== 'vencido') {
                    $m->update(['estado' => 'vencido']);
                }

                $alertas[] = [
                    'mantenimiento_id' => $m->id,
                    'equipo_id' => $equipo->id,
                    'equipo_nombre' => $equipo->nombre,
                    'criticidad' => $criticidad,
                    'fecha_programada' => $m->fecha_programada->toDateString(),
                    'dias_restantes' => $diasRestantes,
                    'tipo_alerta' => 'vencida',
                ];
            } elseif ($diasRestantes <= $diasUmbral) {
                // Alerta preventiva según umbral de criticidad
                $alertas[] = [
                    'mantenimiento_id' => $m->id,
                    'equipo_id' => $equipo->id,
                    'equipo_nombre' => $equipo->nombre,
                    'criticidad' => $criticidad,
                    'fecha_programada' => $m->fecha_programada->toDateString(),
                    'dias_restantes' => $diasRestantes,
                    'tipo_alerta' => 'preventiva',
                ];
            }
        }

        return $alertas;
    }
}
