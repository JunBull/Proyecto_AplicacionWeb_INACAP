<?php

namespace App\Policies;

use App\Models\Compra;
use App\Models\User;

class CompraPolicy
{
    /**
     * Solo Administrador puede listar compras.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Ver detalle de compra (solo Administrador).
     */
    public function view(User $user, Compra $compra): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Crear registro de compra o ingresar por OCR (solo Administrador).
     */
    public function create(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Modificar orden de compra (solo Administrador).
     */
    public function update(User $user, Compra $compra): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Anular compra (solo Admin).
     */
    public function delete(User $user, Compra $compra): bool
    {
        return $user->activo && $user->isAdmin();
    }
}
