<?php

namespace App\Providers;

use App\Models\BajaReemplazo;
use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Insumo;
use App\Models\Mantenimiento;
use App\Models\Proveedor;
use App\Models\SolicitudMaterial;
use App\Models\User;
use App\Policies\BajaPolicy;
use App\Policies\CompraPolicy;
use App\Policies\CotizacionPolicy;
use App\Policies\EntregaPolicy;
use App\Policies\EquipoPolicy;
use App\Policies\FallaPolicy;
use App\Policies\InsumoPolicy;
use App\Policies\MantenimientoPolicy;
use App\Policies\ProveedorPolicy;
use App\Policies\SolicitudMaterialPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Equipo::class, EquipoPolicy::class);
        Gate::policy(Insumo::class, InsumoPolicy::class);
        Gate::policy(SolicitudMaterial::class, SolicitudMaterialPolicy::class);
        Gate::policy(Entrega::class, EntregaPolicy::class);
        Gate::policy(Compra::class, CompraPolicy::class);
        Gate::policy(Cotizacion::class, CotizacionPolicy::class);
        Gate::policy(BajaReemplazo::class, BajaPolicy::class);
        Gate::policy(Proveedor::class, ProveedorPolicy::class);
        Gate::policy(Mantenimiento::class, MantenimientoPolicy::class);
        Gate::policy(Falla::class, FallaPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
