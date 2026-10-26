<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entrega extends Model
{
    use HasFactory;

    protected $table = 'entregas';

    protected $fillable = [
        'solicitud_id',
        'entregado_por',
        'receptor_nombre',
        'receptor_tipo',
        'fecha_entrega',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudMaterial::class, 'solicitud_id');
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class, 'entrega_id');
    }
}
