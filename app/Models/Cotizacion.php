<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'solicitud_cotizacion_id',
        'proveedor_id',
        'monto_total',
        'fecha_recepcion',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'fecha_recepcion' => 'date',
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

    public function adjudicacion(): HasOne
    {
        return $this->hasOne(Adjudicacion::class, 'cotizacion_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}
