<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    use HasFactory;

    protected $table = 'ubicaciones';

    protected $fillable = [
        'carrera_id',
        'padre_id',
        'tipo',
        'nombre',
    ];

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(Ubicacion::class, 'padre_id');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'ubicacion_id');
    }

    public function insumos(): HasMany
    {
        return $this->hasMany(Insumo::class, 'ubicacion_id');
    }
}
