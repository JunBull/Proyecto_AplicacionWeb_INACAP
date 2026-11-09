<?php

namespace App\Services;

use App\Models\BajaReemplazo;
use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Mantenimiento;
use App\Models\Proveedor;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * @param  'preventivo'|'correctivo'  $tipo
     * @param  'semestral'|'anual'|'no_aplica'|null  $frecuencia
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
     * Inicia la intervención técnica de un mantenimiento preventivo o correctivo (BPMN Proceso 4).
     * Transiciona el mantenimiento a 'en_proceso' y el equipo a 'en_mantenimiento' para blindar el stock.
     */
    public function iniciarMantenimiento(Mantenimiento $mantenimiento): Mantenimiento
    {
        if (! in_array($mantenimiento->estado, ['programado', 'vencido'], true)) {
            throw ValidationException::withMessages([
                'mantenimiento' => 'Solo se pueden iniciar mantenimientos en estado programado o vencido.',
            ]);
        }

        return DB::transaction(function () use ($mantenimiento) {
            $mantenimiento->update([
                'estado' => 'en_proceso',
            ]);

            $equipo = $mantenimiento->equipo;
            if ($equipo && $equipo->estado !== 'dado_de_baja') {
                $equipo->update([
                    'estado' => 'en_mantenimiento',
                ]);
            }

            return $mantenimiento->fresh(['equipo', 'proveedor']);
        });
    }

    /**
     * Registra la ejecución de un mantenimiento técnico, adjuntando informe si corresponde
     * y aplicando el veredicto técnico sobre el equipo (conforme, falla o baja).
     *
     * @param  'conforme'|'falla'|'baja'  $veredicto
     * @param array{
     *     descripcion_falla?: string|null,
     *     baja_tipo?: 'baja'|'reemplazo'|null,
     *     baja_motivo?: string|null
     * } $datosVeredicto
     */
    public function registrarEjecucion(
        Mantenimiento $mantenimiento,
        CarbonInterface|string $fechaRealizada,
        ?string $observaciones = null,
        ?UploadedFile $informe = null,
        ?User $subidoPor = null,
        string $veredicto = 'conforme',
        array $datosVeredicto = []
    ): Mantenimiento {
        $fecha = is_string($fechaRealizada) ? Carbon::parse($fechaRealizada) : $fechaRealizada;

        return DB::transaction(function () use ($mantenimiento, $fecha, $observaciones, $informe, $subidoPor, $veredicto, $datosVeredicto) {
            $mantenimiento->update([
                'fecha_realizada' => $fecha->toDateString(),
                'estado' => 'realizado',
                'observaciones' => $observaciones ? trim($observaciones) : $mantenimiento->observaciones,
            ]);

            $subidorId = $subidoPor?->id ?? User::where('rol', 'encargado_panol')->value('id') ?? User::first()?->id ?? 1;
            $rutaInforme = null;

            // Si se adjunta un informe técnico, se respalda en documentos
            if ($informe instanceof UploadedFile) {
                $rutaInforme = $informe->store('mantenimientos/informes', 'public');

                Documento::create([
                    'tipo' => 'informe_tecnico',
                    'ruta' => $rutaInforme,
                    'nombre_original' => $informe->getClientOriginalName(),
                    'hash_sha256' => hash_file('sha256', $informe->getRealPath() ?: $informe->path()),
                    'documentable_type' => Mantenimiento::class,
                    'documentable_id' => $mantenimiento->id,
                    'subido_por' => $subidorId,
                    'creado_en' => now(),
                ]);
            }

            $equipo = $mantenimiento->equipo;
            if ($equipo && $equipo->estado !== 'dado_de_baja') {
                if ($veredicto === 'conforme') {
                    // Declarar equipo operativo y disponible si no tiene otras fallas activas
                    $tieneOtrasFallas = $equipo->fallas()->whereIn('estado', ['reportada', 'en_revision', 'en_reparacion'])->exists();
                    if (! $tieneOtrasFallas) {
                        $equipo->update(['estado' => 'disponible']);
                    }
                } elseif ($veredicto === 'falla') {
                    // El activo permanece fuera de servicio y se registra incidencia formal
                    $equipo->update(['estado' => 'en_mantenimiento']);
                    $descFalla = trim($datosVeredicto['descripcion_falla'] ?? '') ?: 'Falla detectada durante inspección técnica de mantenimiento preventivo.';

                    $falla = Falla::create([
                        'equipo_id' => $equipo->id,
                        'ubicacion_id' => $equipo->ubicacion_id,
                        'reportada_por' => $subidorId,
                        'atendida_por' => $subidorId,
                        'descripcion' => $descFalla,
                        'fecha_reporte' => now(),
                        'estado' => 'en_revision',
                    ]);

                    // Si se cargó informe técnico, también se vincula polimórficamente a la falla
                    if ($informe instanceof UploadedFile && $rutaInforme) {
                        Documento::create([
                            'tipo' => 'informe_tecnico',
                            'ruta' => $rutaInforme,
                            'nombre_original' => $informe->getClientOriginalName(),
                            'hash_sha256' => hash_file('sha256', $informe->getRealPath() ?: $informe->path()),
                            'documentable_type' => Falla::class,
                            'documentable_id' => $falla->id,
                            'subido_por' => $subidorId,
                            'creado_en' => now(),
                        ]);
                    }
                } elseif ($veredicto === 'baja') {
                    // El activo pasa a pendiente de resolución de baja
                    $equipo->update(['estado' => 'pendiente_resolucion']);
                    $tipoBaja = in_array($datosVeredicto['baja_tipo'] ?? '', ['baja', 'reemplazo'], true)
                        ? $datosVeredicto['baja_tipo']
                        : 'baja';
                    $motivoBaja = trim($datosVeredicto['baja_motivo'] ?? '') ?: 'Activo con daño irreparable detectado durante mantenimiento preventivo.';

                    $propuesta = BajaReemplazo::create([
                        'equipo_id' => $equipo->id,
                        'falla_id' => null,
                        'tipo' => $tipoBaja,
                        'motivo' => $motivoBaja,
                        'estado' => 'propuesta',
                        'propuesta_por' => $subidorId,
                        'aprobada_por' => null,
                        'fecha' => now()->toDateString(),
                    ]);

                    // Si se cargó informe técnico, también se vincula polimórficamente a la baja
                    if ($informe instanceof UploadedFile && $rutaInforme) {
                        Documento::create([
                            'tipo' => 'informe_tecnico',
                            'ruta' => $rutaInforme,
                            'nombre_original' => $informe->getClientOriginalName(),
                            'hash_sha256' => hash_file('sha256', $informe->getRealPath() ?: $informe->path()),
                            'documentable_type' => BajaReemplazo::class,
                            'documentable_id' => $propuesta->id,
                            'subido_por' => $subidorId,
                            'creado_en' => now(),
                        ]);
                    }
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
            ->whereIn('estado', ['programado', 'vencido'])
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
                // Ya venció (solo transiciona si aún figuraba como programado)
                if ($m->estado === 'programado') {
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
