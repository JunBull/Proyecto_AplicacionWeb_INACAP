<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Mantenimiento extends Model
{
    use HasFactory;

    protected $table = 'mantenimientos';

    public const SUBTIPOS = [
        'REV-A' => [
            'codigo' => 'REV-A',
            'nombre' => 'Revisión General',
            'tipo_base' => 'preventivo',
            'badge_color' => 'bg-blue-100 text-blue-800 border-blue-200',
            'descripcion' => 'Revisión del estado general y limpieza: inspección de estructuras mecánicas, lubricación, conectores básicos, sensores y tableros con limpieza general.',
        ],
        'REV-B' => [
            'codigo' => 'REV-B',
            'nombre' => 'Mecánica y Eléctrica',
            'tipo_base' => 'preventivo',
            'badge_color' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            'descripcion' => 'Revisión minuciosa de sistemas mecánicos y eléctricos: seguridad de operadores, energización óptima y reemplazo de sellos, filtros, cables, enchufes o relés.',
        ],
        'REV-C' => [
            'codigo' => 'REV-C',
            'nombre' => 'Calibración y Ajuste',
            'tipo_base' => 'preventivo',
            'badge_color' => 'bg-purple-100 text-purple-800 border-purple-200',
            'descripcion' => 'Inspección de precisión, sensibilidad y exactitud: calibración y ajuste según indicaciones del fabricante para asegurar márgenes de error aceptables en docencia.',
        ],
        'PFG' => [
            'codigo' => 'PFG',
            'nombre' => 'Prueba Funcional',
            'tipo_base' => 'preventivo',
            'badge_color' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'descripcion' => 'Prueba de funcionamiento general: protocolo de validación integral previo a disponer el equipo para préstamos o al recibirlo de servicio técnico externo.',
        ],
        'MC-PLAN' => [
            'codigo' => 'MC-PLAN',
            'nombre' => 'Correctivo Programado',
            'tipo_base' => 'correctivo',
            'badge_color' => 'bg-amber-100 text-amber-800 border-amber-200',
            'descripcion' => 'Mantenimiento correctivo planificado: reparación programada con antelación disponiendo de personal técnico, repuestos y manuales de servicio.',
        ],
        'MC-URG' => [
            'codigo' => 'MC-URG',
            'nombre' => 'Correctivo Emergencia',
            'tipo_base' => 'correctivo',
            'badge_color' => 'bg-rose-100 text-rose-800 border-rose-200',
            'descripcion' => 'Mantenimiento correctivo de emergencia: reparación inmediata ante avería imprevista, rotura operativa o condición crítica de seguridad.',
        ],
    ];

    protected $fillable = [
        'equipo_id',
        'proveedor_id',
        'tipo',
        'subtipo',
        'frecuencia',
        'fecha_programada',
        'fecha_realizada',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'fecha_realizada' => 'date',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}
