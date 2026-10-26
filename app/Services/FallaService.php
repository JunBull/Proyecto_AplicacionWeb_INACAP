<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Falla;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
     * Registra la intervención técnica y atención diagnóstica por parte del Encargado de Pañol (CP-06).
     *
     * @param 'en_revision'|'en_reparacion'|'resuelta'|'derivada_a_baja' $nuevoEstado
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
                    ? $falla->descripcion . "\n\n[Atención Técnica " . now()->toDateTimeString() . "]: " . trim($observacionesTecnicas)
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
