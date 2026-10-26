<?php

namespace App\Policies;

use App\Models\Falla;
use App\Models\User;

class FallaPolicy
{
    /**
     * Todos los usuarios activos pueden ver el listado de fallas.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    /**
     * Ver detalle de falla: Administrador, Pañolero o Docente informante.
     */
    public function view(User $user, Falla $falla): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($user->isAdmin() || $user->isPanolero()) {
            return true;
        }

        return (int) $falla->reportada_por === (int) $user->id;
    }

    /**
     * Cualquier usuario activo puede reportar una avería o falla.
     */
    public function create(User $user): bool
    {
        return $user->activo;
    }

    /**
     * Actualizar estado o diagnosticar falla: solo Administrador y Pañolero.
     */
    public function update(User $user, Falla $falla): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Eliminar registro de falla (solo Administrador).
     */
    public function delete(User $user, Falla $falla): bool
    {
        return $user->activo && $user->isAdmin();
    }
}
