<?php

namespace App\Policies;

use App\Models\SolicitudMaterial;
use App\Models\User;

class SolicitudMaterialPolicy
{
    /**
     * Todos los roles pueden ver el listado (filtrado según pertenencia para docentes).
     */
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    /**
     * Docente ve sus propias solicitudes; Pañolero y Admin ven todas.
     */
    public function view(User $user, SolicitudMaterial $solicitud): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($user->isAdmin() || $user->isPanolero()) {
            return true;
        }

        return (int) $solicitud->solicitante_id === (int) $user->id;
    }

    /**
     * Cualquier usuario activo puede crear solicitudes anticipadas.
     */
    public function create(User $user): bool
    {
        return $user->activo;
    }

    /**
     * Modificar una solicitud: Admin/Pañolero en cualquier momento, o Docente si es el dueño y está pendiente.
     */
    public function update(User $user, SolicitudMaterial $solicitud): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($user->isAdmin() || $user->isPanolero()) {
            return true;
        }

        return (int) $solicitud->solicitante_id === (int) $user->id && $solicitud->estado === 'pendiente';
    }

    /**
     * Cancelar solicitud: Admin/Pañolero o Docente dueño mientras esté pendiente.
     */
    public function delete(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->update($user, $solicitud);
    }

    /**
     * Solo Administrador y Pañolero pueden aprobar formalmente solicitudes de préstamo.
     */
    public function aprobar(User $user, SolicitudMaterial $solicitud): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Solo Administrador y Pañolero pueden rechazar solicitudes.
     */
    public function rechazar(User $user, SolicitudMaterial $solicitud): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }
}
