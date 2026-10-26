<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleSolicitud extends Model
{
    use HasFactory;

    protected $table = 'detalle_solicitud';

    protected $fillable = [
        'solicitud_id',
        'insumo_id',
        'equipo_id',
        'cantidad_solicitada',
        'cantidad_entregada',
        'cantidad_devuelta',
        'estado_devolucion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:2',
            'cantidad_entregada' => 'decimal:2',
            'cantidad_devuelta' => 'decimal:2',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudMaterial::class, 'solicitud_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function esRetornable(): bool
    {
        if ($this->equipo_id !== null) {
            return true;
        }

        return (bool) ($this->insumo?->es_retornable ?? false);
    }

    public function getCantidadPendienteAttribute(): float
    {
        return max(0.0, (float) $this->cantidad_entregada - (float) $this->cantidad_devuelta);
    }
}
