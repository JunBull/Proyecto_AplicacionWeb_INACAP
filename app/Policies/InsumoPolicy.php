<?php

namespace App\Policies;

use App\Models\Insumo;
use App\Models\User;

class InsumoPolicy
{
    /**
     * Todos los usuarios activos pueden consultar existencias generales.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    /**
     * Ver ficha de un insumo.
     */
    public function view(User $user, Insumo $insumo): bool
    {
        return $user->activo;
    }

    /**
     * Solo Administrador y Pañolero pueden crear insumos en el inventario.
     */
    public function create(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Solo Administrador y Pañolero pueden actualizar parámetros de insumos.
     */
    public function update(User $user, Insumo $insumo): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Solo Administrador puede eliminar insumos del catálogo base.
     */
    public function delete(User $user, Insumo $insumo): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Solo Administrador y Pañolero pueden registrar ingresos o salidas directas de stock.
     */
    public function ajustarStock(User $user, Insumo $insumo): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }
}
