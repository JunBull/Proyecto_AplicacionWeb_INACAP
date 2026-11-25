<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcategoria extends Model
{
    use HasFactory;

    protected $table = 'subcategorias';

    protected $fillable = [
        'categoria_id',
        'nombre',
        'slug',
        'codigo_prefijo',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'categoria_id' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'subcategoria_id');
    }
}
