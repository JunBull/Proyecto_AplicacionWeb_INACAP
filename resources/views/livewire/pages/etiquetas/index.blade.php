<?php

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Subcategoria;
use App\Services\BarcodeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $codigoLectura = '';
    public ?array $itemEncontrado = null;

    // Filtros en cascada y búsqueda para Equipos
    public string $filtroCategoriaId = '';
    public string $filtroSubcategoriaId = '';
    public string $filtroFamilia = '';
    public bool $soloPendientes = false;
    public string $busquedaEquipo = '';

    // Búsqueda para Insumos
    public string $busquedaInsumo = '';

    // Lote de etiquetas
    public array $seleccionadosEquipos = [];
    public array $seleccionadosInsumos = [];
    /** @var array<int|string, int> */
    public array $copiasInsumos = [];
    public ?string $avisoSeleccionLote = null;

    public ?string $htmlLote = null;
    public ?float $tiempoGeneracionMs = null;
    public int $totalGeneradas = 0;
    public array $itemsLote = [];
    /** @var array<int, int> */
    public array $idsEquiposLote = [];

    public function mount(): void
    {
        Gate::authorize('create', Equipo::class);
    }

    public function updatedFiltroCategoriaId(): void
    {
        $this->filtroSubcategoriaId = '';
        $this->filtroFamilia = '';
        $this->avisoSeleccionLote = null;
    }

    public function updatedFiltroSubcategoriaId(): void
    {
        $this->filtroFamilia = '';
        $this->avisoSeleccionLote = null;
    }

    public function updatedFiltroFamilia(): void
    {
        $this->avisoSeleccionLote = null;
    }

    public function updatedSoloPendientes(): void
    {
        $this->avisoSeleccionLote = null;
    }

    public function updatedSeleccionadosInsumos(): void
    {
        $activos = array_map('intval', $this->seleccionadosInsumos);
        foreach ($activos as $id) {
            if (! isset($this->copiasInsumos[$id]) || (int) $this->copiasInsumos[$id] < 1) {
                $this->copiasInsumos[$id] = 1;
            }
        }

        foreach (array_keys($this->copiasInsumos) as $idKey) {
            if (! in_array((int) $idKey, $activos, true)) {
                unset($this->copiasInsumos[$idKey]);
            }
        }
    }

    public function updatedCopiasInsumos($valor, $key): void
    {
        $copias = (int) $valor;
        if ($copias < 1) {
            $copias = 1;
        } elseif ($copias > BarcodeService::MAX_ETIQUETAS_LOTE) {
            $copias = BarcodeService::MAX_ETIQUETAS_LOTE;
        }

        $this->copiasInsumos[(int) $key] = $copias;
    }

    public function limpiarFiltrosEquipos(): void
    {
        $this->filtroCategoriaId = '';
        $this->filtroSubcategoriaId = '';
        $this->filtroFamilia = '';
        $this->soloPendientes = false;
        $this->busquedaEquipo = '';
        $this->avisoSeleccionLote = null;
    }

    public function leerCodigoBarras(BarcodeService $service): void
    {
        $this->itemEncontrado = $service->buscarPorCodigoONumeroSerie($this->codigoLectura);

        if (! $this->itemEncontrado) {
            $this->addError('codigoLectura', 'No se encontró ningún activo o insumo con el código de barras o número de serie ingresado.');
        } else {
            $this->resetErrorBag('codigoLectura');
            $this->codigoLectura = '';
        }
    }

    public function imprimirEtiquetaIndividual(BarcodeService $service, string $tipo, int $id): void
    {
        if ($tipo === 'equipo') {
            $item = Equipo::with('ubicacion.carrera')->find($id);
        } else {
            $item = Insumo::with('ubicacion.carrera')->find($id);
        }

        if (! $item || empty($item->codigo_barras)) {
            $this->addError('codigoLectura', 'El ítem seleccionado no posee un código de barras válido.');

            return;
        }

        $datosItem = [
            'codigo' => $item->codigo_barras,
            'nombre' => $item->nombre,
            'institucion' => 'INACAP · SEDE TEMUCO',
            'carrera' => $item->ubicacion?->carrera?->nombre,
            'ubicacion' => $item->ubicacion?->nombre ?? 'Pañol Informática',
        ];

        $resultado = $service->generarLoteEtiquetas([$datosItem]);
        $this->htmlLote = $resultado['html'];
        $this->tiempoGeneracionMs = $resultado['tiempo_ms'];
        $this->totalGeneradas = 1;
        $this->itemsLote = [$datosItem];
        $this->idsEquiposLote = $tipo === 'equipo' ? [$item->id] : [];
    }

    public function generarLote(BarcodeService $service): void
    {
        $totalSeleccionados = $this->totalEtiquetasSeleccionadas();

        if ($totalSeleccionados === 0) {
            $this->addError('lote', 'Debe seleccionar al menos un equipo o insumo para generar etiquetas.');

            return;
        }

        if ($totalSeleccionados > BarcodeService::MAX_ETIQUETAS_LOTE) {
            $this->addError('lote', "Ha seleccionado {$totalSeleccionados} etiquetas. El tamaño máximo permitido por tirada es de ".BarcodeService::MAX_ETIQUETAS_LOTE.' etiquetas.');

            return;
        }

        $this->resetErrorBag('lote');
        $items = [];
        $idsEquiposLote = [];

        if (! empty($this->seleccionadosEquipos)) {
            $equipos = Equipo::with('ubicacion.carrera')
                ->whereIn('id', $this->seleccionadosEquipos)
                ->orderBy('codigo_barras')
                ->get();

            foreach ($equipos as $eq) {
                if (! empty($eq->codigo_barras)) {
                    $idsEquiposLote[] = $eq->id;
                    $items[] = [
                        'codigo' => $eq->codigo_barras,
                        'nombre' => $eq->nombre,
                        'institucion' => 'INACAP · SEDE TEMUCO',
                        'carrera' => $eq->ubicacion?->carrera?->nombre,
                        'ubicacion' => $eq->ubicacion?->nombre ?? 'Pañol Informática',
                    ];
                }
            }
        }

        if (! empty($this->seleccionadosInsumos)) {
            $insumos = Insumo::with('ubicacion.carrera')
                ->whereIn('id', $this->seleccionadosInsumos)
                ->orderBy('nombre')
                ->get();

            foreach ($insumos as $ins) {
                if (! empty($ins->codigo_barras)) {
                    $copias = max(1, min(BarcodeService::MAX_ETIQUETAS_LOTE, (int) ($this->copiasInsumos[$ins->id] ?? 1)));
                    $datosInsumo = [
                        'codigo' => $ins->codigo_barras,
                        'nombre' => $ins->nombre,
                        'institucion' => 'INACAP · SEDE TEMUCO',
                        'carrera' => $ins->ubicacion?->carrera?->nombre,
                        'ubicacion' => $ins->ubicacion?->nombre ?? 'Pañol Informática',
                    ];

                    for ($i = 0; $i < $copias; $i++) {
                        $items[] = $datosInsumo;
                    }
                }
            }
        }

        if (empty($items)) {
            $this->addError('lote', 'Ninguno de los ítems seleccionados posee un código de barras asignado.');

            return;
        }

        $resultado = $service->generarLoteEtiquetas($items);
        $this->htmlLote = $resultado['html'];
        $this->tiempoGeneracionMs = $resultado['tiempo_ms'];
        $this->totalGeneradas = $resultado['total'];
        $this->itemsLote = $items;
        $this->idsEquiposLote = $idsEquiposLote;
    }

    public function imprimirLote(): void
    {
        $this->marcarEquiposLoteComoImpresos();
        $this->dispatch('imprimir-etiquetas-lote');
    }

    public function descargarTxtLote()
    {
        if (empty($this->itemsLote)) {
            $this->addError('lote', 'Primero debe generar un lote de etiquetas para poder exportarlo a archivo TXT.');

            return null;
        }

        $this->marcarEquiposLoteComoImpresos();

        $contenido = "CODIGO;NOMBRE;CARRERA;UBICACION;INSTITUCION\r\n";
        foreach ($this->itemsLote as $item) {
            $carrera = $item['carrera'] ?? 'N/A';
            $ubicacion = $item['ubicacion'] ?? 'Pañol Informática';
            $institucion = $item['institucion'] ?? 'INACAP · SEDE TEMUCO';
            $contenido .= "{$item['codigo']};{$item['nombre']};{$carrera};{$ubicacion};{$institucion}\r\n";
        }

        $nombreArchivo = 'lote_etiquetas_'.now()->format('Ymd_His').'.txt';

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombreArchivo, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    protected function marcarEquiposLoteComoImpresos(): void
    {
        if (! empty($this->idsEquiposLote)) {
            Equipo::whereIn('id', $this->idsEquiposLote)->update([
                'etiqueta_impresa' => true,
                'etiqueta_impresa_en' => now(),
            ]);
        }
    }

    public function toggleSeleccionarTodosVisibles(): void
    {
        $visiblesIds = $this->equiposDisponibles()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $actuales = array_map('strval', $this->seleccionadosEquipos);

        if (empty($visiblesIds)) {
            return;
        }

        $todosMarcados = count(array_diff($visiblesIds, $actuales)) === 0;

        if ($todosMarcados) {
            $this->seleccionadosEquipos = array_values(array_diff($actuales, $visiblesIds));
            $this->avisoSeleccionLote = null;

            return;
        }

        $cupoDisponible = max(0, BarcodeService::MAX_ETIQUETAS_LOTE - $this->totalCopiasInsumos());
        $nuevos = $actuales;
        $omitidos = 0;

        foreach ($visiblesIds as $id) {
            if (in_array($id, $nuevos, true)) {
                continue;
            }
            if (count($nuevos) < $cupoDisponible) {
                $nuevos[] = $id;
            } else {
                $omitidos++;
            }
        }

        $this->seleccionadosEquipos = $nuevos;
        $this->avisoSeleccionLote = $omitidos > 0
            ? "Se alcanzó el máximo de ".BarcodeService::MAX_ETIQUETAS_LOTE." etiquetas por tirada. Quedaron {$omitidos} unidad(es) para la siguiente impresión."
            : null;
    }

    public function todosVisiblesSeleccionados(): bool
    {
        $visiblesIds = $this->equiposDisponibles()->pluck('id')->map(fn ($id) => (string) $id)->all();
        if (empty($visiblesIds)) {
            return false;
        }

        $actuales = array_map('strval', $this->seleccionadosEquipos);

        return count(array_diff($visiblesIds, $actuales)) === 0;
    }

    public function limpiarSeleccionEquipos(): void
    {
        $this->seleccionadosEquipos = [];
        $this->avisoSeleccionLote = null;
    }

    public function limpiarSeleccionInsumos(): void
    {
        $this->seleccionadosInsumos = [];
        $this->copiasInsumos = [];
        $this->avisoSeleccionLote = null;
    }

    public function totalCopiasInsumos(): int
    {
        $total = 0;
        foreach ($this->seleccionadosInsumos as $id) {
            $total += max(1, min(BarcodeService::MAX_ETIQUETAS_LOTE, (int) ($this->copiasInsumos[(int) $id] ?? 1)));
        }

        return $total;
    }

    public function totalEtiquetasSeleccionadas(): int
    {
        return count($this->seleccionadosEquipos) + $this->totalCopiasInsumos();
    }

    public function categorias(): Collection
    {
        return Categoria::where('activo', true)->orderBy('orden')->get();
    }

    public function subcategorias(): Collection
    {
        return Subcategoria::where('activo', true)
            ->when($this->filtroCategoriaId !== '', fn ($q) => $q->where('categoria_id', (int) $this->filtroCategoriaId))
            ->orderBy('orden')
            ->get();
    }

    /**
     * Agrupa familias/modelos reales presentes en BD según la categoría/subcategoría activa.
     *
     * @return Collection<int, array{clave: string, etiqueta: string, cantidad: int}>
     */
    public function familiasModelos(): Collection
    {
        return $this->queryEquiposFiltrados(false)
            ->select(['codigo_barras', 'nombre', 'modelo'])
            ->get()
            ->groupBy(fn (Equipo $eq) => $eq->nombre.'||'.$eq->modelo)
            ->map(function (Collection $grupo, string $clave) {
                $primero = $grupo->first();
                $modeloTexto = trim((string) $primero->modelo) !== '' ? " · {$primero->modelo}" : '';
                $codigoBase = preg_replace('/-\d+$/', '', (string) $primero->codigo_barras);
                $prefijoFamilia = $codigoBase && $codigoBase !== $primero->codigo_barras ? "{$codigoBase} · " : '';

                return [
                    'clave' => $clave,
                    'etiqueta' => "{$prefijoFamilia}{$primero->nombre}{$modeloTexto} ({$grupo->count()})",
                    'cantidad' => $grupo->count(),
                ];
            })
            ->sortBy('etiqueta')
            ->values();
    }

    public function equiposDisponibles(): Collection
    {
        return $this->queryEquiposFiltrados(true)
            ->with(['ubicacion.carrera', 'categoria', 'subcategoria'])
            ->orderBy('codigo_barras')
            ->take(100)
            ->get();
    }

    protected function queryEquiposFiltrados(bool $incluirFamilia = true)
    {
        $termino = trim($this->busquedaEquipo);

        $query = Equipo::query()
            ->whereNotNull('codigo_barras')
            ->where('codigo_barras', '!=', '');

        if ($termino === '') {
            // En vista de lote normal excluimos dados de baja (M3)
            $query->where('estado', '!=', 'dado_de_baja');
        }

        if ($this->filtroCategoriaId !== '') {
            $query->where('categoria_id', (int) $this->filtroCategoriaId);
        }

        if ($this->filtroSubcategoriaId !== '') {
            $query->where('subcategoria_id', (int) $this->filtroSubcategoriaId);
        }

        if ($incluirFamilia && $this->filtroFamilia !== '' && str_contains($this->filtroFamilia, '||')) {
            [$nombreFamilia, $modeloFamilia] = explode('||', $this->filtroFamilia, 2);
            $query->where('nombre', $nombreFamilia)->where('modelo', $modeloFamilia);
        }

        if ($this->soloPendientes) {
            $query->where('etiqueta_impresa', false);
        }

        if ($termino !== '') {
            $t = '%'.$termino.'%';
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'LIKE', $t)
                    ->orWhere('codigo_barras', 'LIKE', $t)
                    ->orWhere('marca', 'LIKE', $t)
                    ->orWhere('modelo', 'LIKE', $t)
                    ->orWhere('numero_serie', 'LIKE', $t);
            });
        }

        return $query;
    }

    public function insumosDisponibles(): Collection
    {
        return Insumo::with('ubicacion.carrera')
            ->whereNotNull('codigo_barras')
            ->where('codigo_barras', '!=', '')
            ->when(trim($this->busquedaInsumo) !== '', function ($query) {
                $t = '%'.trim($this->busquedaInsumo).'%';
                $query->where(function ($q) use ($t) {
                    $q->where('nombre', 'LIKE', $t)
                        ->orWhere('codigo_barras', 'LIKE', $t);
                });
            })
            ->orderBy('nombre')
            ->take(100)
            ->get();
    }
}; ?>

<div class="py-6" x-data x-on:imprimir-etiquetas-lote.window="window.print()">
    <style>
        @media print {
            body * {
                visibility: hidden !important;
            }
            #seccion-impresion-etiquetas,
            #seccion-impresion-etiquetas * {
                visibility: visible !important;
            }
            #seccion-impresion-etiquetas {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                border: none !important;
            }
            nav, header, footer, button, .no-print {
                display: none !important;
            }
            @page {
                size: auto;
                margin: 4mm;
            }
            .etiqueta-institucional {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                border-color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .etiqueta-institucional * {
                color: #000000 !important;
            }
        }
    </style>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Encabezado -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-lg shadow-sm border border-gray-100 no-print">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Etiquetas</h1>
            </div>
        </div>

        <!-- Sección 1: Lectura óptica / Escaneo (Código de Barras o S/N) -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 space-y-4 no-print">
            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                Lectura Óptica mediante Pistola / Escáner de Código de Barras
            </h2>
            <div class="flex gap-3 max-w-xl">
                <input wire:model="codigoLectura" wire:keydown.enter="leerCodigoBarras" type="text" placeholder="Escanee o ingrese código de barras o N° de serie..." class="flex-1 text-sm rounded-md border-gray-300 font-mono focus:border-red-500 focus:ring-red-500">
                <button wire:click="leerCodigoBarras" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium rounded-md transition shadow-xs">
                    Buscar
                </button>
            </div>
            @error('codigoLectura') <div class="text-xs text-red-600 font-medium">{{ $message }}</div> @enderror

            @if($itemEncontrado)
                <div class="p-5 bg-green-50 border border-green-200 rounded-lg space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-green-800">
                                    {{ $itemEncontrado['tipo'] === 'equipo' ? 'Equipo Encontrado' : 'Insumo Encontrado' }}
                                </span>
                                <span class="inline-flex px-2 py-0.5 text-[11px] font-bold rounded-full bg-green-200 text-green-900">
                                    {{ ($itemEncontrado['coincidencia'] ?? null) === 'numero_serie' ? 'Recuperado por N° Serie' : 'Recuperación Exacta (CP-12)' }}
                                </span>
                            </div>
                            <div class="text-lg font-bold text-gray-900 mt-1">
                                {{ $itemEncontrado['item']->nombre }}
                            </div>
                            <div class="text-xs text-gray-600 font-mono mt-0.5">
                                Código: <span class="font-bold text-gray-900">{{ $itemEncontrado['item']->codigo_barras }}</span>
                                · Ubicación: {{ $itemEncontrado['item']->ubicacion?->nombre ?? 'N/A' }}
                                @if($itemEncontrado['item']->ubicacion?->carrera)
                                    · Carrera: <span class="font-medium text-gray-800">{{ $itemEncontrado['item']->ubicacion->carrera->nombre }}</span>
                                @endif
                            </div>
                        </div>
                        <button wire:click="imprimirEtiquetaIndividual('{{ $itemEncontrado['tipo'] }}', {{ $itemEncontrado['item']->id }})"
                            class="shrink-0 px-3.5 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-medium rounded-md shadow-xs transition flex items-center gap-1.5 self-start">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Imprimir Etiqueta
                        </button>
                    </div>

                    <div class="pt-3 border-t border-green-200 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        @if($itemEncontrado['tipo'] === 'equipo')
                            @php $eq = $itemEncontrado['item']; @endphp
                            <div>
                                <span class="text-gray-500 block">Marca / Modelo:</span>
                                <span class="font-medium text-gray-900">{{ $eq->marca ?? 'N/A' }} / {{ $eq->modelo ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">N° Serie:</span>
                                <span class="font-mono font-medium text-gray-900">{{ $eq->numero_serie ?? 'S/N' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Categoría / Subcategoría:</span>
                                <span class="font-medium text-gray-900">
                                    {{ $eq->categoria?->nombre ?? 'General' }}
                                    @if($eq->subcategoria)
                                        · {{ $eq->subcategoria->nombre }}
                                    @endif
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Estado Operativo:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $eq->estado === 'disponible' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ ucfirst(str_replace('_', ' ', $eq->estado)) }}
                                </span>
                            </div>
                        @else
                            @php $ins = $itemEncontrado['item']; @endphp
                            <div>
                                <span class="text-gray-500 block">Stock Actual:</span>
                                <span class="font-bold text-gray-900 text-sm">{{ $ins->stock_actual }} {{ $ins->unidad }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Stock Mínimo:</span>
                                <span class="font-medium text-gray-800">{{ $ins->stock_minimo }} {{ $ins->unidad }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Tipo Insumo:</span>
                                <span class="font-medium text-gray-800">{{ $ins->es_retornable ? 'Herramienta Retornable' : 'Consumible' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Nivel de Stock:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $ins->stock_actual <= $ins->stock_minimo ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $ins->stock_actual <= $ins->stock_minimo ? 'Stock Crítico' : 'Normal' }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Sección 2: Generación de Lote de Etiquetas -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 space-y-4">
            @php
                $maxLote = \App\Services\BarcodeService::MAX_ETIQUETAS_LOTE;
                $totalSeleccionados = $this->totalEtiquetasSeleccionadas();
                $equiposLista = $this->equiposDisponibles();
                $insumosLista = $this->insumosDisponibles();
                $hayFiltrosActivos = $filtroCategoriaId !== '' || $filtroSubcategoriaId !== '' || $filtroFamilia !== '' || $soloPendientes || trim($busquedaEquipo) !== '';
            @endphp

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 no-print">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Generar Lote de Etiquetas Institucionales (Hasta {{ $maxLote }} unidades)</h2>
                    <div class="text-xs text-gray-500 mt-0.5">
                        Etiquetas a generar:
                        <span class="font-bold {{ $totalSeleccionados > $maxLote ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $totalSeleccionados }}
                        </span>
                        / {{ $maxLote }} unidades permitidas por tirada
                    </div>
                </div>
                <button wire:click="generarLote" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-medium rounded-md shadow-xs transition">
                    Generar Etiquetas
                </button>
            </div>

            @error('lote') <div class="text-xs text-red-600 font-medium bg-red-50 p-2.5 rounded border border-red-200">{{ $message }}</div> @enderror
            @if($avisoSeleccionLote)
                <div class="text-xs text-amber-800 font-medium bg-amber-50 p-2.5 rounded border border-amber-200 no-print">
                    {{ $avisoSeleccionLote }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 no-print">
                <!-- Selector Equipos en Cascada -->
                <div class="border rounded-md p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="font-bold text-xs uppercase text-gray-600">Equipos con Código de Barras</div>
                        <div class="flex items-center gap-3">
                            @if($hayFiltrosActivos)
                                <button wire:click="limpiarFiltrosEquipos" type="button" class="text-[11px] text-gray-500 hover:text-gray-800 underline">
                                    Limpiar filtros
                                </button>
                            @endif
                            @if(!empty($seleccionadosEquipos))
                                <button wire:click="limpiarSeleccionEquipos" type="button" class="text-[11px] text-red-600 hover:text-red-800 underline">
                                    Deseleccionar ({{ count($seleccionadosEquipos) }})
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Cascada: Categoría -> Subcategoría -> Familia/Modelo -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-0.5">1. Categoría</label>
                            <select wire:model.live="filtroCategoriaId" class="w-full text-xs rounded border-gray-300 py-1.5 focus:border-red-500 focus:ring-red-500">
                                <option value="">Todas las categorías</option>
                                @foreach($this->categorias() as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-0.5">2. Subcategoría</label>
                            <select wire:model.live="filtroSubcategoriaId" class="w-full text-xs rounded border-gray-300 py-1.5 focus:border-red-500 focus:ring-red-500">
                                <option value="">Todas las subcategorías</option>
                                @foreach($this->subcategorias() as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->nombre }} ({{ $sub->codigo_prefijo }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-0.5">3. Familia / Modelo</label>
                        <select wire:model.live="filtroFamilia" class="w-full text-xs rounded border-gray-300 py-1.5 focus:border-red-500 focus:ring-red-500">
                            <option value="">Todos los modelos disponibles</option>
                            @foreach($this->familiasModelos() as $fam)
                                <option value="{{ $fam['clave'] }}">{{ $fam['etiqueta'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Buscador rápido (incluye N° de Serie) + Filtro Pendientes -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                        <input wire:model.live.debounce.300ms="busquedaEquipo" type="text" placeholder="Buscar por nombre, código o N° de serie (S/N)..." class="flex-1 text-xs rounded border-gray-300 py-1.5 focus:border-red-500 focus:ring-red-500">
                        <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 whitespace-nowrap cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="soloPendientes" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            <span>Solo pendientes</span>
                        </label>
                    </div>

                    <!-- Checkbox Maestro -->
                    @if($equiposLista->isNotEmpty())
                        <div class="flex items-center justify-between bg-slate-50 px-2.5 py-1.5 rounded border border-slate-200 text-xs">
                            <label class="inline-flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                                <input type="checkbox"
                                    wire:click="toggleSeleccionarTodosVisibles"
                                    @checked($this->todosVisiblesSeleccionados())
                                    class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <span>Seleccionar todos los visibles ({{ $equiposLista->count() }})</span>
                            </label>
                            <span class="text-[11px] text-slate-500">Orden correlativo</span>
                        </div>
                    @endif

                    <div class="max-h-64 overflow-y-auto space-y-1 text-xs divide-y divide-gray-100">
                        @forelse($equiposLista as $eq)
                            <label wire:key="eq-row-{{ $eq->id }}" class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" wire:model.live="seleccionadosEquipos" value="{{ $eq->id }}" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-gray-800 truncate">{{ $eq->nombre }}</span>
                                        <div class="flex items-center gap-1 shrink-0">
                                            @if($eq->estado === 'dado_de_baja')
                                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-red-100 text-red-700">Baja</span>
                                            @endif
                                            @if($eq->etiqueta_impresa)
                                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-emerald-100 text-emerald-700">Impresa</span>
                                            @else
                                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-amber-100 text-amber-700">Pendiente</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-gray-400 font-mono text-[11px] truncate">
                                        {{ $eq->codigo_barras }}
                                        @if($eq->numero_serie)
                                            · S/N: {{ $eq->numero_serie }}
                                        @endif
                                        @if($eq->ubicacion)
                                            · {{ $eq->ubicacion->nombre }}
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="text-gray-400 py-4 text-center italic">No se encontraron equipos que coincidan con los filtros actuales.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Selector Insumos con Cantidad de Copias -->
                <div class="border rounded-md p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-xs uppercase text-gray-600">Insumos con Código de Barras</div>
                        @if(!empty($seleccionadosInsumos))
                            <button wire:click="limpiarSeleccionInsumos" type="button" class="text-[11px] text-red-600 hover:text-red-800 underline">
                                Deseleccionar ({{ count($seleccionadosInsumos) }})
                            </button>
                        @endif
                    </div>

                    <!-- Buscador reactivo insumos -->
                    <div>
                        <input wire:model.live.debounce.300ms="busquedaInsumo" type="text" placeholder="Buscar insumo por nombre o código..." class="w-full text-xs rounded border-gray-300 py-1.5 focus:border-red-500 focus:ring-red-500">
                    </div>

                    <div class="max-h-80 overflow-y-auto space-y-1 text-xs divide-y divide-gray-100">
                        @forelse($insumosLista as $ins)
                            @php $estaMarcado = in_array((string) $ins->id, array_map('strval', $seleccionadosInsumos), true); @endphp
                            <div wire:key="ins-row-{{ $ins->id }}" class="flex items-center justify-between gap-2 p-1.5 hover:bg-gray-50 rounded">
                                <label class="flex items-center gap-2 flex-1 min-w-0 cursor-pointer">
                                    <input type="checkbox" wire:model.live="seleccionadosInsumos" value="{{ $ins->id }}" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-800 truncate">{{ $ins->nombre }}</div>
                                        <div class="text-gray-400 font-mono text-[11px] truncate">
                                            {{ $ins->codigo_barras }}
                                            @if($ins->ubicacion)
                                                · {{ $ins->ubicacion->nombre }}
                                            @endif
                                        </div>
                                    </div>
                                </label>
                                @if($estaMarcado)
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="text-[11px] text-gray-500 font-medium">Copias:</span>
                                        <input type="number"
                                            min="1"
                                            max="{{ $maxLote }}"
                                            wire:model.live.debounce.200ms="copiasInsumos.{{ $ins->id }}"
                                            class="w-16 text-xs text-center rounded border-gray-300 py-1 focus:border-red-500 focus:ring-red-500">
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-gray-400 py-4 text-center italic">No se encontraron insumos disponibles con código de barras.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Visualización del Lote y Botones de Impresión / Descarga -->
            @if($htmlLote)
                <div class="border-t pt-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between bg-gray-50 p-3 rounded border border-gray-200 no-print gap-2">
                        <div class="text-xs text-gray-700">
                            Lote preparado: <span class="font-bold text-gray-900">{{ $totalGeneradas }}</span> etiqueta(s) generada(s)
                            @if($tiempoGeneracionMs)
                                en <span class="font-mono text-gray-600">{{ $tiempoGeneracionMs }} ms</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="descargarTxtLote" class="text-xs bg-white hover:bg-gray-100 text-gray-800 font-medium px-3.5 py-2 rounded border border-gray-300 shadow-xs transition flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Descargar TXT
                            </button>
                            <button wire:click="imprimirLote" class="text-xs bg-gray-800 hover:bg-gray-900 text-white font-medium px-4 py-2 rounded shadow-xs transition flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Imprimir Etiquetas
                            </button>
                        </div>
                    </div>

                    <div id="seccion-impresion-etiquetas" class="bg-gray-50 p-4 rounded border">
                        {!! $htmlLote !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
