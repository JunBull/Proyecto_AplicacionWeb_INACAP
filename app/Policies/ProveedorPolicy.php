<?php

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;

class ProveedorPolicy
{
    /**
     * Solo Administrador puede listar proveedores.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Ver ficha de proveedor (solo Administrador).
     */
    public function view(User $user, Proveedor $proveedor): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Registrar proveedor en directorio (solo Administrador).
     */
    public function create(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Modificar proveedor o alternar estado activo/inactivo (solo Administrador).
     */
    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Eliminar proveedor (solo Administrador).
     */
    public function delete(User $user, Proveedor $proveedor): bool
    {
        return $user->activo && $user->isAdmin();
    }
}
