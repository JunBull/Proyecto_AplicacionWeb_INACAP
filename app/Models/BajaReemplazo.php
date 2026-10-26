<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BajaReemplazo extends Model
{
    use HasFactory;

    protected $table = 'bajas_reemplazos';

    protected $fillable = [
        'equipo_id',
        'falla_id',
        'tipo',
        'motivo',
        'estado',
        'propuesta_por',
        'aprobada_por',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function falla(): BelongsTo
    {
        return $this->belongsTo(Falla::class, 'falla_id');
    }

    public function proponente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propuesta_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}
