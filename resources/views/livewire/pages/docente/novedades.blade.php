<?php

use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Services\FallaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public bool $mostrarModalReporte = false;
    public string $tipoItem = 'equipo';
    public ?int $equipoId = null;
    public ?int $insumoId = null;
    public ?int $ubicacionId = null;
    public string $descripcion = '';
    public string $severidad = 'media';
    public string $filtroEstado = 'todos';

    public function mount(): void
    {
        $this->inicializarFormulario();
    }

    public function inicializarFormulario(): void
    {
        $this->tipoItem = 'equipo';
        $this->equipoId = Equipo::first()?->id;
        $this->insumoId = Insumo::first()?->id;
        $primerLab = Ubicacion::where('tipo', 'laboratorio')->first() ?? Ubicacion::first();
        $this->ubicacionId = $primerLab?->id;
        $this->descripcion = '';
        $this->severidad = 'media';
    }

    public function abrirModalReporte(): void
    {
        $this->resetValidation();
        $this->inicializarFormulario();
        $this->mostrarModalReporte = true;
    }

    public function cerrarModalReporte(): void
    {
        $this->mostrarModalReporte = false;
        $this->resetValidation();
    }

    public function registrarNovedad(FallaService $fallaService): void
    {
        $this->validate([
            'descripcion' => 'required|string|min:10',
            'ubicacionId' => 'required|exists:ubicaciones,id',
        ], [
            'descripcion.required' => 'Debe ingresar una descripción detallada de la novedad o desperfecto técnico.',
            'descripcion.min' => 'La descripción debe tener al menos 10 caracteres.',
            'ubicacionId.required' => 'Debe indicar el laboratorio o sala donde se detectó la novedad.',
        ]);

        try {
            $falla = $fallaService->reportarIncidencia(Auth::user(), [
                'tipo_item' => $this->tipoItem,
                'equipo_id' => $this->tipoItem === 'equipo' ? $this->equipoId : null,
                'insumo_id' => $this->tipoItem === 'insumo' ? $this->insumoId : null,
                'ubicacion_id' => $this->ubicacionId,
                'descripcion' => $this->descripcion,
                'severidad' => $this->severidad,
            ]);

            $this->mostrarModalReporte = false;
            session()->flash('mensaje', "¡Novedad #{$falla->id} reportada exitosamente! Se ha generado el informe formal en PDF y se notificó por correo a Pañol y Administración.");
            session()->flash('pdf_url', asset("storage/incidencias/Reporte_Incidencia_{$falla->id}.pdf"));
        } catch (\Exception $e) {
            $this->addError('novedad', $e->getMessage());
        }
    }

    public function misNovedades()
    {
        $query = Falla::with(['equipo', 'insumo', 'ubicacion', 'reportador'])
            ->where('reportada_por', Auth::id())
            ->orderByDesc('id');

        if ($this->filtroEstado !== 'todos') {
            $query->where('estado', $this->filtroEstado);
        }

        return $query->get();
    }

    public function laboratoriosDisponibles()
    {
        return Ubicacion::whereIn('tipo', ['laboratorio', 'taller', 'panol'])->orderBy('nombre')->get();
    }

    public function equiposDisponibles()
    {
        return Equipo::orderBy('nombre')->get();
    }

    public function insumosDisponibles()
    {
        return Insumo::orderBy('nombre')->get();
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Encabezado del Módulo -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-7 h-7 text-amber-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Informe de Novedades
            </h1>
        </div>
        <div>
            <button
                wire:click="abrirModalReporte"
                class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 min-h-[44px] bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm rounded-lg shadow-sm hover:shadow transition active:scale-95"
            >
                <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Reportar novedad</span>
            </button>
        </div>
    </div>

    <!-- Alertas Flash -->
    @if(session()->has('mensaje'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm font-medium">{{ session('mensaje') }}</span>
            </div>
            @if(session()->has('pdf_url'))
                <a
                    href="{{ session('pdf_url') }}"
                    target="_blank"
                    class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-xs transition shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Descargar Informe PDF
                </a>
            @endif
        </div>
    @endif

    @php
        $novedades = $this->misNovedades();
        $totalNovedades = $novedades->count();
        $totalPendientes = $novedades->whereIn('estado', ['reportada', 'en_diagnostico', 'en_mantenimiento'])->count();
        $totalResueltas = $novedades->where('estado', 'resuelta')->count();
    @endphp

    <!-- Panel de Métricas Rápidas -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Reportadas</div>
                <div class="text-2xl font-extrabold text-slate-900 mt-0.5">{{ $totalNovedades }}</div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-orange-50 text-orange-700 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">En Atención / Diagnóstico</div>
                <div class="text-2xl font-extrabold text-orange-700 mt-0.5">{{ $totalPendientes }}</div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Novedades Resueltas</div>
                <div class="text-2xl font-extrabold text-emerald-700 mt-0.5">{{ $totalResueltas }}</div>
            </div>
        </div>
    </div>

    <!-- Contenedor Principal: Historial de Novedades -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Historial de Novedades e Incidencias Técnicas</h2>
                <p class="text-xs text-slate-500 mt-0.5">Seguimiento formal de reportes enviados con respaldo institucional en PDF.</p>
            </div>
            <!-- Filtro de Estado -->
            <div class="flex items-center gap-2">
                <label class="text-xs text-slate-500 font-medium">Filtrar por estado:</label>
                <select
                    wire:model.live="filtroEstado"
                    class="text-xs border-slate-300 rounded-lg focus:ring-amber-500 focus:border-amber-500 py-1.5"
                >
                    <option value="todos">Todas</option>
                    <option value="reportada">Reportada</option>
                    <option value="en_diagnostico">En Diagnóstico</option>
                    <option value="en_mantenimiento">En Mantenimiento</option>
                    <option value="resuelta">Resuelta</option>
                </select>
            </div>
        </div>

        @if($novedades->isEmpty())
            <div class="text-center py-16">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-slate-800">No registra novedades técnicas</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    Si detecta algún desperfecto, rotura o anomalía en equipos o insumos de taller, presione el botón superior "Reportar novedad".
                </p>
                <button
                    wire:click="abrirModalReporte"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Reportar novedad ahora
                </button>
            </div>
        @else
            <div class="space-y-4">
                @foreach($novedades as $inc)
                    <div class="p-4 sm:p-5 rounded-xl border border-slate-200/80 hover:border-slate-300 transition bg-white shadow-2xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 border-b border-slate-100 pb-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-bold text-xs text-slate-800 bg-slate-100 px-2 py-0.5 rounded">#NOV-{{ str_pad($inc->id, 5, '0', STR_PAD_LEFT) }}</span>
                                <x-status-badge :estado="$inc->estado" />
                                <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded {{ $inc->severidad === 'critica' ? 'bg-red-100 text-red-700 border border-red-200' : ($inc->severidad === 'alta' ? 'bg-orange-100 text-orange-700 border border-orange-200' : 'bg-yellow-100 text-yellow-800 border border-yellow-200') }}">
                                    Severidad {{ ucfirst($inc->severidad ?? 'Media') }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-xs text-slate-400">
                                    {{ \Carbon\Carbon::parse($inc->fecha_reporte)->translatedFormat('d M Y, H:i') }}
                                </span>
                                <a
                                    href="{{ asset('storage/incidencias/Reporte_Incidencia_' . $inc->id . '.pdf') }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold rounded-lg border border-amber-200 shadow-2xs transition"
                                >
                                    <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span>Ver PDF</span>
                                </a>
                            </div>
                        </div>

                        <div class="mt-3 text-xs sm:text-sm text-slate-700">
                            <div class="font-semibold text-slate-900 flex items-center gap-2">
                                @if($inc->equipo)
                                    <span class="px-2 py-0.5 text-[10px] bg-blue-50 text-blue-700 rounded font-bold uppercase border border-blue-200">Equipo</span>
                                    <span>{{ $inc->equipo->nombre }}</span>
                                    <span class="text-xs text-slate-500 font-normal">(Marca: {{ $inc->equipo->marca }} | Serie: {{ $inc->equipo->numero_serie }})</span>
                                @elseif($inc->insumo)
                                    <span class="px-2 py-0.5 text-[10px] bg-purple-50 text-purple-700 rounded font-bold uppercase border border-purple-200">Insumo</span>
                                    <span>{{ $inc->insumo->nombre }}</span>
                                    <span class="text-xs text-slate-500 font-normal">(Código: {{ $inc->insumo->codigo_barras }})</span>
                                @endif
                            </div>

                            <p class="mt-2 text-xs sm:text-sm text-slate-600 bg-slate-50/80 p-3 rounded-lg border border-slate-100 italic">
                                "{{ $inc->descripcion }}"
                            </p>

                            <div class="mt-3 text-[11px] text-slate-400 flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-t border-slate-50 pt-2">
                                <span>Laboratorio / Ubicación: <strong class="text-slate-700">{{ $inc->ubicacion?->nombre ?? 'Pañol Central' }}</strong></span>
                                <span>Reportado por: <strong class="text-slate-700">{{ $inc->reportador?->name }}</strong></span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- MODAL: Reportar Novedad Técnica (CP-28) -->
    @if($mostrarModalReporte)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150 border border-slate-200">
                <div class="px-5 py-4 bg-amber-600 text-white flex items-center justify-between">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Reportar Novedad Técnica
                    </h3>
                    <button wire:click="cerrarModalReporte" class="text-white/80 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 sm:p-6 space-y-4 max-h-[80vh] overflow-y-auto text-xs sm:text-sm">
                    <p class="text-xs text-slate-500">
                        Al enviar esta novedad, el sistema generará automáticamente un informe PDF formal con hash criptográfico y notificará de inmediato al personal de Pañol y Administración.
                    </p>

                    @error('novedad')
                        <div class="p-3 rounded-lg bg-red-50 text-red-700 text-xs font-semibold">
                            {{ $message }}
                        </div>
                    @enderror

                    <!-- Selección de Tipo: Equipo o Insumo -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Tipo de Recurso Afectado *</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer {{ $tipoItem === 'equipo' ? 'border-amber-500 bg-amber-50 text-amber-900 font-bold' : 'border-slate-200 text-slate-700' }}">
                                <input type="radio" wire:model.live="tipoItem" value="equipo" class="text-amber-600 focus:ring-amber-500">
                                <span>Equipo / Maquinaria</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer {{ $tipoItem === 'insumo' ? 'border-amber-500 bg-amber-50 text-amber-900 font-bold' : 'border-slate-200 text-slate-700' }}">
                                <input type="radio" wire:model.live="tipoItem" value="insumo" class="text-amber-600 focus:ring-amber-500">
                                <span>Insumo / Material</span>
                            </label>
                        </div>
                    </div>

                    <!-- Selector específico según tipo -->
                    @if($tipoItem === 'equipo')
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Seleccione el Equipo Afectado *</label>
                            <select wire:model="equipoId" class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                @foreach($this->equiposDisponibles() as $eq)
                                    <option value="{{ $eq->id }}">{{ $eq->nombre }} (Marca: {{ $eq->marca }} | S/N: {{ $eq->numero_serie }})</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Seleccione el Insumo Afectado *</label>
                            <select wire:model="insumoId" class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                @foreach($this->insumosDisponibles() as $ins)
                                    <option value="{{ $ins->id }}">{{ $ins->nombre }} (Código: {{ $ins->codigo_barras }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Ubicación / Sala donde se detectó -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Laboratorio / Taller *</label>
                            <select wire:model="ubicacionId" class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                @foreach($this->laboratoriosDisponibles() as $lab)
                                    <option value="{{ $lab->id }}">{{ $lab->nombre }}</option>
                                @endforeach
                            </select>
                            @error('ubicacionId') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <!-- Severidad -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nivel de Severidad</label>
                            <select wire:model="severidad" class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                <option value="baja">Baja (Anomalía estética o menor)</option>
                                <option value="media">Media (Falla parcial / funcional)</option>
                                <option value="alta">Alta (Equipo inoperativo / riesgo eléctrico)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Descripción del desperfecto -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Descripción del Desperfecto o Avería *</label>
                        <textarea wire:model="descripcion" rows="4" placeholder="Detalle los síntomas, componentes dañados o fallas detectadas..." class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500"></textarea>
                        @error('descripcion') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button wire:click="cerrarModalReporte" type="button" class="px-4 py-2 text-xs sm:text-sm font-semibold text-slate-600 hover:bg-slate-200 rounded-lg transition">
                        Cancelar
                    </button>
                    <button wire:click="registrarNovedad" type="button" class="px-5 py-2 text-xs sm:text-sm font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm transition active:scale-95">
                        Registrar novedad
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
