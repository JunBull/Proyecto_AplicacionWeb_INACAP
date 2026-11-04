<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asignatura extends Model
{
    use HasFactory;

    protected $table = 'asignaturas';

    protected $fillable = [
        'carrera_id',
        'codigo',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'asignatura_usuario', 'asignatura_id', 'usuario_id')
            ->withTimestamps();
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudMaterial::class, 'asignatura_id');
    }
}
