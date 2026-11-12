<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Falla extends Model
{
    use HasFactory;

    protected $table = 'fallas';

    protected $fillable = [
        'equipo_id',
        'insumo_id',
        'ubicacion_id',
        'tipo_destino',
        'edificio_destino',
        'espacio_destino',
        'reportada_por',
        'atendida_por',
        'descripcion',
        'fecha_reporte',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_reporte' => 'datetime',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function reportador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportada_por');
    }

    public function atendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }

    public function bajasReemplazos(): HasMany
    {
        return $this->hasMany(BajaReemplazo::class, 'falla_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}
