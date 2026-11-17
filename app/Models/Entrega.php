<?php

namespace App\Models;

use Carbon\Carbon;
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
        'receptor_credencial_rut',
        'credencial_verificada',
        'hora_entrega',
        'hora_devolucion_acordada',
        'reportado_en',
        'fecha_entrega',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'reportado_en' => 'datetime',
            'credencial_verificada' => 'boolean',
        ];
    }

    public function getFechaHoraAcordadaAttribute(): ?Carbon
    {
        if (! $this->hora_devolucion_acordada || ! $this->fecha_entrega) {
            return null;
        }

        $hora = trim($this->hora_devolucion_acordada);
        $dt = Carbon::parse($this->fecha_entrega->format('Y-m-d').' '.$hora);
        if ($dt->lessThan($this->fecha_entrega)) {
            $dt->addDay();
        }

        return $dt;
    }

    public function getEstaVencidaAttribute(): bool
    {
        $acordada = $this->fecha_hora_acordada;

        return $acordada ? now()->greaterThan($acordada) : false;
    }

    public function getMinutosRetrasoAttribute(): int
    {
        $acordada = $this->fecha_hora_acordada;
        if (! $acordada || now()->lessThanOrEqualTo($acordada)) {
            return 0;
        }

        return (int) now()->diffInMinutes($acordada);
    }

    public function getRetrasoHumanoAttribute(): string
    {
        $acordada = $this->fecha_hora_acordada;
        if (! $acordada || now()->lessThanOrEqualTo($acordada)) {
            return 'Sin retraso';
        }

        return now()->diffForHumans($acordada, [
            'syntax' => Carbon::DIFF_ABSOLUTE,
            'parts' => 2,
        ]);
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
