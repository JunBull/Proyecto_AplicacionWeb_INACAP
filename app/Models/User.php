<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'name',
        'email',
        'password',
        'rol',
        'activo',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rol' => 'docente',
        'activo' => true,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Compatibility accessor & mutator for 'name' attribute.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->nombre ?? '',
            set: fn (?string $value): array => [
                'nombre' => $value ?? '',
            ]
        );
    }

    public function isAdmin(): bool
    {
        return $this->rol === 'administrador';
    }

    public function isPanolero(): bool
    {
        return $this->rol === 'encargado_panol';
    }

    public function isDocente(): bool
    {
        return $this->rol === 'docente';
    }

    public function carreras(): BelongsToMany
    {
        return $this->belongsToMany(Carrera::class, 'carrera_usuario', 'usuario_id', 'carrera_id')->withTimestamps();
    }

    public function fallasReportadas(): HasMany
    {
        return $this->hasMany(Falla::class, 'reportada_por');
    }

    public function fallasAtendidas(): HasMany
    {
        return $this->hasMany(Falla::class, 'atendida_por');
    }

    public function bajasPropuestas(): HasMany
    {
        return $this->hasMany(BajaReemplazo::class, 'propuesta_por');
    }

    public function bajasAprobadas(): HasMany
    {
        return $this->hasMany(BajaReemplazo::class, 'aprobada_por');
    }

    public function documentosSubidos(): HasMany
    {
        return $this->hasMany(Documento::class, 'subido_por');
    }

    public function solicitudesCotizacion(): HasMany
    {
        return $this->hasMany(SolicitudCotizacion::class, 'creada_por');
    }

    public function adjudicaciones(): HasMany
    {
        return $this->hasMany(Adjudicacion::class, 'adjudicada_por');
    }

    public function movimientosPresupuesto(): HasMany
    {
        return $this->hasMany(MovimientoPresupuesto::class, 'usuario_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'usuario_id');
    }

    public function solicitudesMaterial(): HasMany
    {
        return $this->hasMany(SolicitudMaterial::class, 'solicitante_id');
    }

    public function solicitudesRevisadas(): HasMany
    {
        return $this->hasMany(SolicitudMaterial::class, 'revisada_por');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'entregado_por');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class, 'recibido_por');
    }
}
