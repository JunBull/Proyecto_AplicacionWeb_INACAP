<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\User;

class EquipoPolicy
{
    /**
     * Determina si el usuario puede ver el catálogo general de equipos.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    /**
     * Determina si el usuario puede ver la ficha técnica del equipo.
     */
    public function view(User $user, Equipo $equipo): bool
    {
        return $user->activo;
    }

    /**
     * Solo Administrador y Pañolero pueden crear nuevos equipos.
     */
    public function create(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Solo Administrador y Pañolero pueden actualizar fichas de equipos.
     */
    public function update(User $user, Equipo $equipo): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Solo el Administrador puede eliminar definitivamente un equipo del catálogo.
     */
    public function delete(User $user, Equipo $equipo): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Docentes, Pañoleros y Administradores pueden reportar novedades y fallas.
     */
    public function reportarFalla(User $user, Equipo $equipo): bool
    {
        return $user->activo;
    }

    /**
     * Solo Administrador y Pañolero pueden resolver o diagnosticar fallas de un equipo.
     */
    public function resolverFalla(User $user, Equipo $equipo): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }
}
