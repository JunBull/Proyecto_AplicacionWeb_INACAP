<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'razon_social',
        'rut',
        'correo',
        'telefono',
        'tipo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class, 'proveedor_id');
    }

    public function enviosCotizacion(): HasMany
    {
        return $this->hasMany(EnvioCotizacion::class, 'proveedor_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'proveedor_id');
    }
}
