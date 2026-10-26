<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Adjudicacion extends Model
{
    use HasFactory;

    protected $table = 'adjudicaciones';

    protected $fillable = [
        'cotizacion_id',
        'adjudicada_por',
        'excepcion_autorizada',
        'justificacion',
    ];

    protected function casts(): array
    {
        return [
            'excepcion_autorizada' => 'boolean',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function adjudicador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjudicada_por');
    }

    public function compra(): HasOne
    {
        return $this->hasOne(Compra::class, 'adjudicacion_id');
    }
}
