<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    use HasFactory;

    protected $table = 'equipos';

    protected $fillable = [
        'codigo_barras',
        'nombre',
        'marca',
        'modelo',
        'numero_serie',
        'estado',
        'criticidad',
        'ubicacion_id',
        'compra_id',
        'fotografia',
        'fecha_adquisicion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_adquisicion' => 'date',
        ];
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'equipo_id');
    }

    public function fallas(): HasMany
    {
        return $this->hasMany(Falla::class, 'equipo_id');
    }

    public function bajasReemplazos(): HasMany
    {
        return $this->hasMany(BajaReemplazo::class, 'equipo_id');
    }

    public function detallesSolicitud(): HasMany
    {
        return $this->hasMany(DetalleSolicitud::class, 'equipo_id');
    }
}
