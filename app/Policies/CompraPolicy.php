<?php

namespace App\Policies;

use App\Models\Compra;
use App\Models\User;

class CompraPolicy
{
    /**
     * Solo Pañolero y Administrador pueden listar compras.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Ver detalle de compra.
     */
    public function view(User $user, Compra $compra): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Crear registro de compra o ingresar por OCR.
     */
    public function create(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Modificar orden de compra.
     */
    public function update(User $user, Compra $compra): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Anular compra (solo Admin).
     */
    public function delete(User $user, Compra $compra): bool
    {
        return $user->activo && $user->isAdmin();
    }
}
