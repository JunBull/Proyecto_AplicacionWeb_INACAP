<?php

use App\Models\ParametroSistema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $stock_minimo_clases_default = 5;
    public int $alerta_mantenimiento_dias = 90;
    public int $minimo_cotizaciones_adjudicacion = 3;
    public bool $notificar_incidencias_pdf = true;

    public function mount(): void
    {
        if (Auth::user()->rol !== 'administrador') {
            abort(403, 'Acceso restringido exclusivamente a Administradores.');
        }

        $this->stock_minimo_clases_default = (int) ParametroSistema::get('stock_minimo_clases_default', 5);
        $this->alerta_mantenimiento_dias = (int) ParametroSistema::get('alerta_mantenimiento_dias', 90);
        $this->minimo_cotizaciones_adjudicacion = (int) ParametroSistema::get('minimo_cotizaciones_adjudicacion', 3);
        $this->notificar_incidencias_pdf = (bool) ParametroSistema::get('notificar_incidencias_pdf', true);
    }

    public function guardar(): void
    {
        $this->validate([
            'stock_minimo_clases_default' => 'required|integer|min:0|max:100',
            'alerta_mantenimiento_dias' => 'required|integer|min:1|max:365',
            'minimo_cotizaciones_adjudicacion' => 'required|integer|in:2,3',
            'notificar_incidencias_pdf' => 'boolean',
        ], [
            'stock_minimo_clases_default.required' => 'El stock mínimo de clases es obligatorio.',
            'stock_minimo_clases_default.min' => 'El stock mínimo no puede ser negativo.',
            'alerta_mantenimiento_dias.required' => 'La frecuencia de alertas es obligatoria.',
            'minimo_cotizaciones_adjudicacion.in' => 'El mínimo de cotizaciones debe ser 2 o 3 según la normativa institucional.',
        ]);

        ParametroSistema::set(
            'stock_minimo_clases_default',
            $this->stock_minimo_clases_default,
            'integer',
            'Cantidad piso de unidades operativas reservadas para docencia en pañol'
        );

        ParametroSistema::set(
            'alerta_mantenimiento_dias',
            $this->alerta_mantenimiento_dias,
            'integer',
            'Frecuencia de alerta preventiva para revisión de equipamiento (días)'
        );

        ParametroSistema::set(
            'minimo_cotizaciones_adjudicacion',
            $this->minimo_cotizaciones_adjudicacion,
            'integer',
            'Mínimo de cotizaciones válidas para adjudicar compras (2 o 3)'
        );

        ParametroSistema::set(
            'notificar_incidencias_pdf',
            $this->notificar_incidencias_pdf,
            'boolean',
            'Activa el despacho por cola de correo del PDF formal de incidencias a Pañoleros y Administradores'
        );

        session()->flash('mensaje', 'Parámetros del sistema actualizados en tiempo real.');
    }
}; ?>

<div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Encabezado -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-red-100 text-red-800 border border-red-200">
                Administración
            </span>
            <span class="text-xs text-slate-400 font-mono">v1.1</span>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 mt-1">Parámetros Globales del Sistema</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Configuración de umbrales operativos, reglas de adquisición y notificaciones asíncronas (Sección 3.1).</p>
    </div>

    @if(session()->has('mensaje'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-600 text-emerald-800 text-xs sm:text-sm rounded-r-lg">
            {{ session('mensaje') }}
        </div>
    @endif

    <!-- Formulario de Parámetros -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
        <!-- 1. Stock Mínimo Reservado para Clases -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start pb-5 border-b border-slate-100">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-slate-900">Stock Mínimo Reservado para Clases (Piso Operativo)</label>
                <p class="text-xs text-slate-500 mt-0.5">
                    Cantidad piso de unidades que deben permanecer reservadas en pañol para talleres docentes antes de autorizar préstamos a estudiantes.
                </p>
            </div>
            <div>
                <input wire:model="stock_minimo_clases_default" type="number" min="0" max="100" class="w-full text-sm rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                @error('stock_minimo_clases_default') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- 2. Mínimo de Cotizaciones para Adjudicación -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start pb-5 border-b border-slate-100">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-slate-900">Mínimo de Cotizaciones para Adjudicación</label>
                <p class="text-xs text-slate-500 mt-0.5">
                    Número mínimo de cotizaciones de proveedores requeridas para adjudicar una compra sin requerir justificación excepcional.
                </p>
            </div>
            <div>
                <select wire:model="minimo_cotizaciones_adjudicacion" class="w-full text-sm rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                    <option value="2">2 Proveedores (Mínimo Flexible)</option>
                    <option value="3">3 Proveedores (Norma Institucional Estándar)</option>
                </select>
                @error('minimo_cotizaciones_adjudicacion') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- 3. Frecuencia de Alertas de Mantenimiento -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start pb-5 border-b border-slate-100">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-slate-900">Frecuencia de Alerta Preventiva de Mantenimiento</label>
                <p class="text-xs text-slate-500 mt-0.5">
                    Intervalo en días para emitir alertas automáticas en el dashboard sobre equipos que requieren mantención técnica periódica.
                </p>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <input wire:model="alerta_mantenimiento_dias" type="number" min="1" max="365" class="w-full text-sm rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                    <span class="text-xs text-slate-500 font-semibold">días</span>
                </div>
                @error('alerta_mantenimiento_dias') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- 4. Notificaciones PDF de Incidencias -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-slate-900">Despacho Asíncrono de Reportes PDF de Incidencias</label>
                <p class="text-xs text-slate-500 mt-0.5">
                    Al registrarse una anomalía o avería docente, despacha automáticamente el PDF oficial a Pañoleros y Administradores.
                </p>
            </div>
            <div class="flex items-center h-10">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input wire:model="notificar_incidencias_pdf" type="checkbox" class="rounded text-red-600 focus:ring-red-600 w-5 h-5">
                    <span class="text-xs font-semibold text-slate-700">{{ $notificar_incidencias_pdf ? 'Habilitado' : 'Deshabilitado' }}</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end pt-4 border-t border-slate-100">
            <button wire:click="guardar" type="button" class="px-6 py-2.5 bg-red-700 hover:bg-red-800 text-white font-semibold text-xs sm:text-sm rounded-lg shadow-sm transition">
                Guardar Configuración Global
            </button>
        </div>
    </div>
</div>
