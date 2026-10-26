<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id',
        'insumo_id',
        'descripcion',
        'cantidad',
        'costo_unitario',
        'impuesto',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'impuesto' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
