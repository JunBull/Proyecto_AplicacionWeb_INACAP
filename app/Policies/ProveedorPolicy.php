<?php

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;

class ProveedorPolicy
{
    /**
     * Pañolero y Administrador pueden listar proveedores.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Ver ficha de proveedor.
     */
    public function view(User $user, Proveedor $proveedor): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Registrar proveedor en directorio.
     */
    public function create(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Modificar proveedor o alternar estado activo/inactivo.
     */
    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Eliminar proveedor (solo Administrador).
     */
    public function delete(User $user, Proveedor $proveedor): bool
    {
        return $user->activo && $user->isAdmin();
    }
}
