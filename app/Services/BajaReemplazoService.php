<?php

namespace App\Services;

use App\Models\BajaReemplazo;
use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BajaReemplazoService
{
    /**
     * Registra una propuesta formal de baja o reemplazo con sustento técnico-económico y respaldos adjuntos (CP-07).
     *
     * @param 'baja'|'reemplazo' $tipo
     * @param array<int, UploadedFile> $documentosAdjuntos
     */
    public function proponerBajaOReemplazo(
        Equipo $equipo,
        User $proponente,
        string $tipo,
        string $motivo,
        ?int $fallaId = null,
        array $documentosAdjuntos = []
    ): BajaReemplazo {
        if (! in_array($tipo, ['baja', 'reemplazo'], true)) {
            throw new InvalidArgumentException("Tipo de propuesta inválido: {$tipo}");
        }

        $motivoLimpio = trim($motivo);
        if ($motivoLimpio === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Debe ingresar la justificación técnica y económica que sustenta el costo de reparación vs. reposición.',
            ]);
        }

        if ($fallaId && ! Falla::where('id', $fallaId)->where('equipo_id', $equipo->id)->exists()) {
            throw ValidationException::withMessages([
                'falla_id' => 'La falla asociada no pertenece al equipo seleccionado.',
            ]);
        }

        return DB::transaction(function () use ($equipo, $proponente, $tipo, $motivoLimpio, $fallaId, $documentosAdjuntos) {
            $propuesta = BajaReemplazo::create([
                'equipo_id' => $equipo->id,
                'falla_id' => $fallaId,
                'tipo' => $tipo,
                'motivo' => $motivoLimpio,
                'estado' => 'propuesta',
                'propuesta_por' => $proponente->id,
                'aprobada_por' => null,
                'fecha' => now()->toDateString(),
            ]);

            // Almacenar documentos respaldatorios (informes técnicos / cotizaciones de reposición)
            foreach ($documentosAdjuntos as $archivo) {
                if ($archivo instanceof UploadedFile) {
                    $ruta = $archivo->store('bajas/documentos', 'public');
                    $tipoDoc = str_contains(strtolower($archivo->getClientOriginalName()), 'cotiz') ? 'cotizacion' : 'informe_tecnico';

                    Documento::create([
                        'tipo' => $tipoDoc,
                        'ruta' => $ruta,
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'hash_sha256' => hash_file('sha256', $archivo->getRealPath() ?: $archivo->path()),
                        'documentable_type' => BajaReemplazo::class,
                        'documentable_id' => $propuesta->id,
                        'subido_por' => $proponente->id,
                        'creado_en' => now(),
                    ]);
                }
            }

            // Cambiar estado preventivo del activo
            $equipo->update(['estado' => 'pendiente_resolucion']);

            return $propuesta->fresh(['equipo', 'documentos', 'proponente']);
        });
    }

    /**
     * Resuelve la propuesta (aprobación o rechazo exclusivo por el Administrador) (CP-07).
     */
    public function resolverPropuesta(
        BajaReemplazo $propuesta,
        User $administrador,
        bool $aprobada,
        ?string $resolucionObservaciones = null
    ): BajaReemplazo {
        if ($administrador->rol !== 'administrador') {
            throw new InvalidArgumentException('Solo un usuario con rol administrador puede aprobar o rechazar bajas o reemplazos.');
        }

        if ($propuesta->estado !== 'propuesta') {
            throw new InvalidArgumentException('Esta propuesta ya ha sido resuelta con anterioridad.');
        }

        return DB::transaction(function () use ($propuesta, $administrador, $aprobada, $resolucionObservaciones) {
            $nuevoEstado = $aprobada ? 'aprobada' : 'rechazada';

            $propuesta->update([
                'estado' => $nuevoEstado,
                'aprobada_por' => $administrador->id,
                'motivo' => $resolucionObservaciones
                    ? $propuesta->motivo . "\n\n[Resolución Administrador " . now()->toDateTimeString() . "]: " . trim($resolucionObservaciones)
                    : $propuesta->motivo,
            ]);

            $equipo = $propuesta->equipo;

            if ($aprobada) {
                // El equipo queda inhabilitado de forma definitiva para préstamos
                $equipo->update(['estado' => 'dado_de_baja']);
            } else {
                // Si es rechazada, se mantiene no disponible pendiente de nueva revisión
                $equipo->update(['estado' => 'no_disponible']);
            }

            return $propuesta->fresh(['equipo', 'aprobador', 'documentos']);
        });
    }

    /**
     * Verifica si un equipo está formalmente habilitado para préstamos.
     */
    public function equipoHabilitadoParaPrestamo(Equipo $equipo): bool
    {
        return $equipo->estado === 'disponible';
    }
}
