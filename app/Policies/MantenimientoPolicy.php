<?php

namespace App\Policies;

use App\Models\Mantenimiento;
use App\Models\User;

class MantenimientoPolicy
{
    /**
     * Pañolero y Administrador gestionan el programa de mantenimiento preventivo y correctivo.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Ver ficha de mantenimiento.
     */
    public function view(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Programar nuevo mantenimiento.
     */
    public function create(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Actualizar o completar orden de mantenimiento con informe técnico.
     */
    public function update(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Cancelar o eliminar mantenimiento programado (solo Administrador).
     */
    public function delete(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->activo && $user->isAdmin();
    }
}
