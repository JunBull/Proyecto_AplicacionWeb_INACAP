<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Compra extends Model
{
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = [
        'adjudicacion_id',
        'proveedor_id',
        'carrera_id',
        'numero_documento',
        'fecha',
        'monto_total',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_total' => 'decimal:2',
        ];
    }

    public function adjudicacion(): BelongsTo
    {
        return $this->belongsTo(Adjudicacion::class, 'adjudicacion_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'compra_id');
    }

    public function movimientosPresupuesto(): HasMany
    {
        return $this->hasMany(MovimientoPresupuesto::class, 'compra_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'compra_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}
