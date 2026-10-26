<?php

namespace App\Policies;

use App\Models\Entrega;
use App\Models\User;

class EntregaPolicy
{
    /**
     * Solo Pañolero y Administrador pueden acceder a la bitácora de entregas y despachos.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Ver detalle de una entrega específica.
     */
    public function view(User $user, Entrega $entrega): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Despachar materiales en mesón.
     */
    public function create(User $user): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Actualizar entrega.
     */
    public function update(User $user, Entrega $entrega): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }

    /**
     * Eliminar entrega (solo Admin en caso excepcional).
     */
    public function delete(User $user, Entrega $entrega): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Procesar devoluciones de materiales prestados.
     */
    public function procesarDevolucion(User $user, Entrega $entrega): bool
    {
        return $user->activo && ($user->isAdmin() || $user->isPanolero());
    }
}
