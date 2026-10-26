<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'insumo_id',
        'usuario_id',
        'compra_id',
        'solicitud_id',
        'tipo',
        'cantidad',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudMaterial::class, 'solicitud_id');
    }
}
