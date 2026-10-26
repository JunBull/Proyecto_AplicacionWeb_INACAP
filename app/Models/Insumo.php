<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insumo extends Model
{
    use HasFactory;

    protected $table = 'insumos';

    protected $fillable = [
        'codigo_barras',
        'nombre',
        'unidad',
        'es_retornable',
        'stock_actual',
        'stock_minimo',
        'ubicacion_id',
    ];

    protected function casts(): array
    {
        return [
            'es_retornable' => 'boolean',
            'stock_actual' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
        ];
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function compraDetalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'insumo_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'insumo_id');
    }

    public function detallesSolicitud(): HasMany
    {
        return $this->hasMany(DetalleSolicitud::class, 'insumo_id');
    }
}
