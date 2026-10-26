<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Solo Administrador puede ver el catálogo de cuentas de usuario del sistema.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Ver perfil de usuario.
     */
    public function view(User $user, User $model): bool
    {
        return $user->activo && ($user->isAdmin() || (int) $user->id === (int) $model->id);
    }

    /**
     * Crear nuevos usuarios: Exclusivo de Administrador.
     */
    public function create(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Modificar usuario (datos o rol): Solo Administrador, o el usuario editando sus datos propios (sin alterar su rol).
     */
    public function update(User $user, User $model): bool
    {
        return $user->activo && ($user->isAdmin() || (int) $user->id === (int) $model->id);
    }

    /**
     * Eliminar usuario: Exclusivo de Administrador.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->activo && $user->isAdmin() && (int) $user->id !== (int) $model->id;
    }
}
