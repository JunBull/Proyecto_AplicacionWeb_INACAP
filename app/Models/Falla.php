<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Falla extends Model
{
    use HasFactory;

    protected $table = 'fallas';

    protected $fillable = [
        'equipo_id',
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
}
