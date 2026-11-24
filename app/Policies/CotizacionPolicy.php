<?php

namespace App\Policies;

use App\Models\Cotizacion;
use App\Models\User;

class CotizacionPolicy
{
    /**
     * Solo Administrador puede ver cotizaciones.
     */
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Ver cotización específica (solo Administrador).
     */
    public function view(User $user, Cotizacion $cotizacion): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Registrar propuestas de cotización de proveedores (solo Administrador).
     */
    public function create(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Actualizar datos de cotización (solo Administrador).
     */
    public function update(User $user, Cotizacion $cotizacion): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Eliminar propuesta de cotización (solo Admin).
     */
    public function delete(User $user, Cotizacion $cotizacion): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Adjudicar formalmente una compra: Exclusivo del Administrador (CP-18 / Matriz 2.2).
     */
    public function adjudicar(User $user, Cotizacion $cotizacion): bool
    {
        return $user->activo && $user->isAdmin();
    }

    /**
     * Autorizar excepción de cuota mínima de cotizaciones: Exclusivo del Administrador.
     */
    public function autorizarExcepcion(User $user): bool
    {
        return $user->activo && $user->isAdmin();
    }
}
