<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudMaterial extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_material';

    protected $fillable = [
        'solicitante_id',
        'revisada_por',
        'fecha_solicitud',
        'fecha_requerida',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'fecha_requerida' => 'date',
        ];
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleSolicitud::class, 'solicitud_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'solicitud_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'solicitud_id');
    }
}
