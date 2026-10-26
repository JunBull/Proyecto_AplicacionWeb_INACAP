<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Presupuesto extends Model
{
    use HasFactory;

    protected $table = 'presupuestos';

    protected $fillable = [
        'carrera_id',
        'anio',
        'monto_asignado',
        'saldo_actual',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'monto_asignado' => 'decimal:2',
            'saldo_actual' => 'decimal:2',
        ];
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoPresupuesto::class, 'presupuesto_id');
    }
}
