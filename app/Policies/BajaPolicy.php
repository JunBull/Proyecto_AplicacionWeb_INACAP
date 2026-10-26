<?php

namespace App\Policies;

use App\Models\BajaReemplazo;
use App\Models\User;

class BajaPolicy
{
    /**
     * Pañolero y Administrador pueden consultar el historial de bajas.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Ver expediente de baja.
     */
    public function view(User $user, BajaReemplazo $baja): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Proponer informe técnico de baja y reemplazo (Pañolero o Admin).
     */
    public function create(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Proponer baja técnica.
     */
    public function proponer(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Aprobar definitivamente la baja técnica de un activo: Exclusivo del Administrador (CP-07).
     */
    public function aprobar(User $user, BajaReemplazo $baja): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Eliminar solicitud de baja (solo Admin).
     */
    public function delete(User $user, BajaReemplazo $baja): bool
    {
        return $user->activo && $user->isAdmin();
    }
}
