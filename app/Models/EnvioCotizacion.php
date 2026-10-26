<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvioCotizacion extends Model
{
    use HasFactory;

    protected $table = 'envios_cotizacion';

    protected $fillable = [
        'solicitud_cotizacion_id',
        'proveedor_id',
        'estado',
        'intentos',
        'enviado_en',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'intentos' => 'integer',
            'enviado_en' => 'datetime',
        ];
    }

    public function solicitudCotizacion(): BelongsTo
    {
        return $this->belongsTo(SolicitudCotizacion::class, 'solicitud_cotizacion_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
