<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Documento extends Model
{
    use HasFactory;

    protected $table = 'documentos';

    protected $fillable = [
        'tipo',
        'ruta',
        'nombre_original',
        'hash_sha256',
        'documentable_type',
        'documentable_id',
        'subido_por',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
