<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devolucion extends Model
{
    use HasFactory;

    protected $table = 'devoluciones';

    protected $fillable = [
        'entrega_id',
        'recibido_por',
        'fecha_devolucion',
        'conforme',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_devolucion' => 'datetime',
            'conforme' => 'boolean',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
    }

    public function recibidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }
}
