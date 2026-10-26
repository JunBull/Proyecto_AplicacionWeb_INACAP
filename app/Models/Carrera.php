<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrera extends Model
{
    use HasFactory;

    protected $table = 'carreras';

    protected $fillable = [
        'codigo',
        'nombre',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'carrera_usuario', 'carrera_id', 'usuario_id')->withTimestamps();
    }

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(Ubicacion::class, 'carrera_id');
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class, 'carrera_id');
    }

    public function solicitudesCotizacion(): HasMany
    {
        return $this->hasMany(SolicitudCotizacion::class, 'carrera_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'carrera_id');
    }
}
