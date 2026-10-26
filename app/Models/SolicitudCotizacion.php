<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudCotizacion extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_cotizacion';

    protected $fillable = [
        'carrera_id',
        'creada_por',
        'descripcion',
        'estado',
        'fecha_creacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_creacion' => 'datetime',
        ];
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function envios(): HasMany
    {
        return $this->hasMany(EnvioCotizacion::class, 'solicitud_cotizacion_id');
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class, 'solicitud_cotizacion_id');
    }
}
