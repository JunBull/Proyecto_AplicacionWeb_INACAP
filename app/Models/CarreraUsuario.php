<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CarreraUsuario extends Pivot
{
    protected $table = 'carrera_usuario';

    public $incrementing = false;

    protected $fillable = [
        'usuario_id',
        'carrera_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }
}
