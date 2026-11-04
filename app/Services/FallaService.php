<?php

namespace App\Services;

use App\Jobs\EnviarReporteIncidenciaJob;
use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Insumo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FallaService
{
    /**
     * Registra un reporte de anomalía/falla por parte de un docente o pañolero (CP-06).
     * El activo cambia preventivamente de estado a 'en_mantenimiento' para impedir nuevos préstamos.
     */
    public function reportarFalla(Equipo $equipo, User $informante, string $descripcion): Falla
    {
        $descripcionLimpia = trim($descripcion);
        if ($descripcionLimpia === '') {
            throw ValidationException::withMessages([
                'descripcion' => 'Debe ingresar una descripción detallada de la anomalía o desperfecto detectado.',
            ]);
        }

        return DB::transaction(function () use ($equipo, $informante, $descripcionLimpia) {
            $falla = Falla::create([
                'equipo_id' => $equipo->id,
                'reportada_por' => $informante->id,
                'atendida_por' => null,
                'descripcion' => $descripcionLimpia,
                'fecha_reporte' => now(),
                'estado' => 'reportada',
            ]);

            // Bloqueo preventivo del activo
            $equipo->update([
                'estado' => 'en_mantenimiento',
            ]);

            return $falla->fresh(['equipo', 'reportador']);
        });
    }

    /**
     * Reporte integral de incidencia técnica de equipos o insumos con generación de PDF
     * formal y despacho asíncrono a pañoleros y administradores (CP-28, SLA-02).
     *
     * @param array{
     *     tipo_item: 'equipo'|'insumo',
     *     equipo_id?: ?int,
     *     insumo_id?: ?int,
     *     ubicacion_id?: ?int,
     *     descripcion: string,
     *     severidad?: ?string
     * } $datos
     */
    public function reportarIncidencia(User $informante, array $datos): Falla
    {
        $descripcionLimpia = isset($datos['descripcion']) ? trim($datos['descripcion']) : '';
        if ($descripcionLimpia === '') {
            throw ValidationException::withMessages([
                'descripcion' => 'Debe ingresar una descripción detallada de la anomalía observada.',
            ]);
        }

        $tipoItem = $datos['tipo_item'] ?? null;
        $equipoId = ! empty($datos['equipo_id']) ? (int) $datos['equipo_id'] : null;
        $insumoId = ! empty($datos['insumo_id']) ? (int) $datos['insumo_id'] : null;
        $ubicacionId = ! empty($datos['ubicacion_id']) ? (int) $datos['ubicacion_id'] : null;

        if ($tipoItem === 'equipo' && ! $equipoId) {
            throw ValidationException::withMessages([
                'equipo_id' => 'Debe seleccionar el equipo afectado por la incidencia.',
            ]);
        }

        if ($tipoItem === 'insumo' && ! $insumoId) {
            throw ValidationException::withMessages([
                'insumo_id' => 'Debe seleccionar el insumo afectado por la incidencia.',
            ]);
        }

        // Restricción exclusiva: exactamente uno debe estar definido
        if (($equipoId === null && $insumoId === null) || ($equipoId !== null && $insumoId !== null)) {
            throw ValidationException::withMessages([
                'recurso' => 'La incidencia debe referenciar exclusivamente a un equipo o a un insumo.',
            ]);
        }

        return DB::transaction(function () use ($informante, $equipoId, $insumoId, $ubicacionId, $descripcionLimpia) {
            if ($equipoId !== null) {
                /** @var Equipo $equipo */
                $equipo = Equipo::where('id', $equipoId)->lockForUpdate()->firstOrFail();
                $equipo->update(['estado' => 'en_mantenimiento']);
                if ($ubicacionId === null) {
                    $ubicacionId = $equipo->ubicacion_id;
                }
            }

            if ($insumoId !== null && $ubicacionId === null) {
                $insumo = Insumo::where('id', $insumoId)->first();
                if ($insumo) {
                    $ubicacionId = $insumo->ubicacion_id;
                }
            }

            $falla = Falla::create([
                'equipo_id' => $equipoId,
                'insumo_id' => $insumoId,
                'ubicacion_id' => $ubicacionId,
                'reportada_por' => $informante->id,
                'atendida_por' => null,
                'descripcion' => $descripcionLimpia,
                'fecha_reporte' => now(),
                'estado' => 'reportada',
            ]);

            // Generar documento PDF institucional
            $pdfService = app(PdfService::class);
            $pdfContent = $pdfService->generarReporteIncidencia($falla);
            $rutaArchivo = "incidencias/Reporte_Incidencia_{$falla->id}.pdf";
            Storage::disk('public')->put($rutaArchivo, $pdfContent);
            $hashSha256 = hash('sha256', $pdfContent);

            // Registro en la tabla polimórfica 'documentos'
            Documento::create([
                'tipo' => 'informe_tecnico',
                'ruta' => $rutaArchivo,
                'nombre_original' => "Reporte_Incidencia_{$falla->id}.pdf",
                'hash_sha256' => $hashSha256,
                'documentable_type' => Falla::class,
                'documentable_id' => $falla->id,
                'subido_por' => $informante->id,
            ]);

            // Despacho asíncrono por cola de correo (SLA-02)
            EnviarReporteIncidenciaJob::dispatch($falla);

            return $falla->fresh(['equipo', 'insumo', 'ubicacion', 'reportador', 'documentos']);
        });
    }

    /**
     * Registra la intervención técnica y atención diagnóstica por parte del Encargado de Pañol (CP-06).
     *
     * @param  'en_revision'|'en_reparacion'|'resuelta'|'derivada_a_baja'  $nuevoEstado
     */
    public function atenderFalla(
        Falla $falla,
        User $panolero,
        string $nuevoEstado,
        ?string $observacionesTecnicas = null
    ): Falla {
        $estadosValidos = ['en_revision', 'en_reparacion', 'resuelta', 'derivada_a_baja'];

        if (! in_array($nuevoEstado, $estadosValidos, true)) {
            throw new InvalidArgumentException("Estado de atención inválido: {$nuevoEstado}");
        }

        return DB::transaction(function () use ($falla, $panolero, $nuevoEstado, $observacionesTecnicas) {
            $falla->update([
                'atendida_por' => $panolero->id,
                'estado' => $nuevoEstado,
                'descripcion' => $observacionesTecnicas
                    ? $falla->descripcion."\n\n[Atención Técnica ".now()->toDateTimeString().']: '.trim($observacionesTecnicas)
                    : $falla->descripcion,
            ]);

            $equipo = $falla->equipo;

            if ($nuevoEstado === 'resuelta') {
                $equipo->update(['estado' => 'disponible']);
            } elseif ($nuevoEstado === 'derivada_a_baja') {
                $equipo->update(['estado' => 'pendiente_resolucion']);
            } elseif (in_array($nuevoEstado, ['en_revision', 'en_reparacion'], true)) {
                $equipo->update(['estado' => 'en_mantenimiento']);
            }

            return $falla->fresh(['equipo', 'reportador', 'atendedor']);
        });
    }
}
