<?php

use App\Exceptions\DocumentoDuplicadoException;
use App\Exceptions\SaldoInsuficienteException;
use App\Models\Carrera;
use App\Models\Compra;
use App\Models\Insumo;
use App\Models\Proveedor;
use App\Services\CompraService;
use App\Services\OcrService;
use App\Services\PresupuestoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    // Filtros
    public ?int $filtroCarreraId = null;

    // Estado del asistente de Recepción e Ingreso Documental (Proceso 2)
    public bool $mostrarModalIngreso = false;
    public int $pasoActual = 1; // 1: Carga y OCR, 2: Revisión Humana y Asociación, 3: Confirmación

    // Archivo y Extracción
    public $archivoDocumento = null;
    public bool $procesandoOcr = false;
    public ?string $ocrMensaje = null;
    public ?float $ocrConfianza = null;

    // Datos del formulario de revisión (CP-08 y CP-09)
    public ?int $proveedor_id = null;
    public ?int $carrera_id = null;
    public string $numero_documento = '';
    public string $fecha = '';
    public string $tipo_documento = 'factura';

    // Líneas de ítems extraídas o digitadas manualmente (CP-08 / CP-09)
    public array $items = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Compra::class);
        $this->fecha = date('Y-m-d');
        $primeraCarrera = Carrera::where('activa', true)->first();
        if ($primeraCarrera) {
            $this->carrera_id = $primeraCarrera->id;
        }
    }

    public function compras(CompraService $service)
    {
        return $service->listarCompras($this->filtroCarreraId);
    }

    public function abrirModalIngreso(): void
    {
        Gate::authorize('create', Compra::class);
        $this->reset(['archivoDocumento', 'ocrMensaje', 'ocrConfianza', 'numero_documento', 'items']);
        $this->pasoActual = 1;
        $this->fecha = date('Y-m-d');
        $this->items = [
            ['insumo_id' => null, 'descripcion' => '', 'cantidad' => 1, 'costo_unitario' => 0, 'impuesto' => 0]
        ];
        $this->mostrarModalIngreso = true;
    }

    /**
     * Procesa la extracción OCR del documento cargado sin modificar existencias ni presupuesto (CP-08).
     */
    public function procesarOcr(OcrService $ocrService): void
    {
        Gate::authorize('create', Compra::class);

        $this->validate([
            'archivoDocumento' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'archivoDocumento.required' => 'Seleccione un archivo de factura u orden de compra.',
            'archivoDocumento.file' => 'El elemento cargado debe ser un archivo válido.',
            'archivoDocumento.mimes' => 'El archivo debe tener formato PDF, JPG o PNG.',
            'archivoDocumento.max' => 'El archivo no debe exceder 10 MB de tamaño.',
        ]);

        $this->procesandoOcr = true;

        try {
            // Verificar duplicidad previa por hash SHA-256 (CP-10)
            $hash = $ocrService->calcularHash($this->archivoDocumento);
            $ocrService->validarNoDuplicado($hash);

            $resultado = $ocrService->extraerDatos($this->archivoDocumento);

            if ($resultado['ocr_exitoso']) {
                $this->ocrConfianza = $resultado['confianza'];
                $this->numero_documento = $resultado['numero_documento'] ?? '';
                $this->fecha = $resultado['fecha'] ?? date('Y-m-d');

                // Si detectó RUT de proveedor, intentar mapear al proveedor existente
                if (! empty($resultado['proveedor_rut'])) {
                    $prov = Proveedor::where('rut', $resultado['proveedor_rut'])->first();
                    if ($prov) {
                        $this->proveedor_id = $prov->id;
                    }
                }

                // Cargar ítems sugeridos por OCR para revisión humana
                if (! empty($resultado['items'])) {
                    $this->items = [];
                    foreach ($resultado['items'] as $item) {
                        // Buscar si existe un insumo con nombre similar para sugerir vinculación
                        $insumoSugerido = Insumo::where('nombre', 'LIKE', '%' . trim($item['descripcion']) . '%')->first();

                        $this->items[] = [
                            'insumo_id' => $insumoSugerido?->id,
                            'descripcion' => $item['descripcion'],
                            'cantidad' => $item['cantidad'],
                            'costo_unitario' => $item['costo_unitario'],
                            'impuesto' => $item['impuesto'],
                        ];
                    }
                }

                $this->ocrMensaje = 'Datos extraídos con éxito por el motor OCR. Proceda a la revisión y validación manual.';
            } else {
                $this->ocrMensaje = $resultado['error'] ?? 'El documento no pudo ser procesado automáticamente. Digite los campos manualmente.';
            }

            // Avanza a la pantalla obligatoria de revisión del pañolero (CP-08, CP-09)
            $this->pasoActual = 2;
        } catch (DocumentoDuplicadoException $e) {
            $this->addError('archivoDocumento', $e->getMessage());
        } finally {
            $this->procesandoOcr = false;
        }
    }

    public function agregarLinea(): void
    {
        $this->items[] = [
            'insumo_id' => null,
            'descripcion' => '',
            'cantidad' => 1,
            'costo_unitario' => 0,
            'impuesto' => 0,
        ];
    }

    public function eliminarLinea(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function calcularTotalPropuesto(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $cant = (float) ($item['cantidad'] ?? 0);
            $costo = (float) ($item['costo_unitario'] ?? 0);
            $imp = (float) ($item['impuesto'] ?? 0);
            $total += ($cant * $costo) + $imp;
        }
        return $total;
    }

    /**
     * Confirma la compra tras revisión y corrección humana, ejecutando la transacción atómica (CP-09, CP-15, PI-01).
     */
    public function confirmarIngresoInventario(CompraService $compraService): void
    {
        Gate::authorize('create', Compra::class);

        try {
            $compraService->registrarCompraEIngreso(
                [
                    'proveedor_id' => (int) $this->proveedor_id,
                    'carrera_id' => (int) $this->carrera_id,
                    'numero_documento' => $this->numero_documento,
                    'fecha' => $this->fecha,
                    'tipo_documento' => $this->tipo_documento,
                ],
                $this->items,
                Auth::user(),
                $this->archivoDocumento
            );

            $this->reset(['mostrarModalIngreso', 'archivoDocumento', 'items', 'numero_documento', 'ocrMensaje']);
            session()->flash('mensaje', 'Compra confirmada, presupuesto debitado e inventario actualizado exitosamente en transacción atómica (PI-01, CP-09, CP-15).');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (SaldoInsuficienteException $e) {
            $this->addError('presupuesto', $e->getMessage());
        } catch (DocumentoDuplicadoException $e) {
            $this->addError('duplicidad', $e->getMessage());
        }
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session()->has('mensaje'))
            <div class="p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-sm rounded shadow">
                {{ session('mensaje') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Proceso 2: Recepción e Ingreso Documental con OCR</h2>
                    <p class="text-sm text-gray-500">Reconocimiento asistido, supervisión humana innegociable, deducción presupuestaria y kardex atómicos (CP-08, CP-09, CP-10, CP-15, PI-01)</p>
                </div>
                <button
                    wire:click="abrirModalIngreso"
                    class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-md shadow"
                >
                    + Registrar Ingreso por Factura / OC
                </button>
            </div>

            <!-- Tabla de Compras Registradas -->
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left">Folio / N° Documento</th>
                            <th class="px-4 py-3 text-left">Proveedor</th>
                            <th class="px-4 py-3 text-left">Carrera</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-right">Monto Total</th>
                            <th class="px-4 py-3 text-center">Ítems Ingresados</th>
                            <th class="px-4 py-3 text-center">Respaldo Digital</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($this->compras(app(\App\Services\CompraService::class)) as $c)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-bold text-gray-900">{{ $c->numero_documento }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $c->proveedor->razon_social ?? 'Proveedor' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $c->carrera->nombre ?? 'Carrera' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $c->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900">${{ number_format($c->monto_total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs font-semibold">
                                        {{ $c->detalles->count() }} ítem(s)
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($c->documentos->isNotEmpty())
                                        <span class="text-xs text-green-700 font-medium" title="Hash: {{ $c->documentos->first()->hash_sha256 }}">
                                            ✓ SHA-256 Verificado
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">Sin archivo</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No hay compras ingresadas en el inventario.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Ingreso Documental y Revisión Humana (CP-08, CP-09, CP-10) -->
        @if($mostrarModalIngreso)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full p-6 space-y-5">
                    <div class="flex justify-between items-center border-b pb-3">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">
                                @if($pasoActual === 1) Paso 1: Carga y Reconocimiento Documental (OCR)
                                @else Paso 2: Revisión, Corrección y Confirmación Manual
                                @endif
                            </h3>
                            <p class="text-xs text-gray-500">Regla BPMN Proceso 2: Los datos extraídos por OCR jamás alteran stock ni saldo sin supervisión humana directa (CP-08, CP-09).</p>
                        </div>
                        <button wire:click="$set('mostrarModalIngreso', false)" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                    </div>

                    @error('duplicidad')
                        <div class="p-3 bg-red-100 border-l-4 border-red-600 text-red-800 text-xs rounded">
                            {{ $message }}
                        </div>
                    @enderror
                    @error('presupuesto')
                        <div class="p-3 bg-red-100 border-l-4 border-red-600 text-red-800 text-xs rounded">
                            {{ $message }}
                        </div>
                    @enderror

                    @if($pasoActual === 1)
                        <!-- Paso 1: Carga del Archivo -->
                        <div class="space-y-4">
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                                <label class="cursor-pointer block">
                                    <span class="text-sm font-semibold text-red-700 hover:text-red-800">Seleccionar Factura u Orden de Compra (PDF, PNG, JPG, TXT)</span>
                                    <input type="file" wire:model="archivoDocumento" class="hidden" />
                                </label>
                                @if($archivoDocumento)
                                    <p class="text-xs text-green-700 mt-2 font-semibold">Archivo seleccionado: {{ $archivoDocumento->getClientOriginalName() }}</p>
                                @endif
                                <p class="text-[11px] text-gray-400 mt-1">El sistema computa el hash SHA-256 para prevenir el procesamiento duplicado del documento (CP-10).</p>
                                @error('archivoDocumento') <span class="block text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex justify-between items-center pt-2">
                                <button
                                    type="button"
                                    wire:click="$set('pasoActual', 2)"
                                    class="text-xs text-gray-600 underline"
                                >
                                    Saltar OCR y digitar manualmente
                                </button>
                                <button
                                    type="button"
                                    wire:click="procesarOcr(app(\App\Services\OcrService::class))"
                                    class="px-5 py-2 bg-red-700 hover:bg-red-800 text-white rounded-md text-sm font-semibold"
                                >
                                    Procesar con Motor OCR
                                </button>
                            </div>
                        </div>
                    @else
                        <!-- Paso 2: Revisión Humana Innegociable y Asociación al Catálogo -->
                        <form wire:submit.prevent="confirmarIngresoInventario(app(\App\Services\CompraService::class))" class="space-y-4">
                            @if($ocrMensaje)
                                <div class="p-3 bg-blue-50 border border-blue-200 text-blue-800 text-xs rounded">
                                    <strong>Estado OCR:</strong> {{ $ocrMensaje }}
                                    @if($ocrConfianza)
                                        <span class="ml-2 px-2 py-0.5 bg-blue-200 text-blue-900 rounded font-semibold text-[10px]">Confianza: {{ round($ocrConfianza * 100) }}%</span>
                                    @endif
                                </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 bg-gray-50 p-3 rounded-lg border">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase">Proveedor *</label>
                                    <select wire:model="proveedor_id" class="w-full border-gray-300 rounded text-xs p-1.5">
                                        <option value="">Seleccione proveedor...</option>
                                        @foreach(Proveedor::where('activo', true)->orderBy('razon_social')->get() as $p)
                                            <option value="{{ $p->id }}">{{ $p->razon_social }}</option>
                                        @endforeach
                                    </select>
                                    @error('proveedor_id') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase">Carrera (Presupuesto) *</label>
                                    <select wire:model="carrera_id" class="w-full border-gray-300 rounded text-xs p-1.5">
                                        @foreach(Carrera::where('activa', true)->get() as $c)
                                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('carrera_id') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase">N° Documento / Folio *</label>
                                    <input type="text" wire:model="numero_documento" class="w-full border-gray-300 rounded text-xs p-1.5" placeholder="Ej. F-12894" />
                                    @error('numero_documento') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase">Fecha Documento *</label>
                                    <input type="date" wire:model="fecha" class="w-full border-gray-300 rounded text-xs p-1.5" />
                                </div>
                            </div>

                            <!-- Tabla de Líneas de Ítems (CP-09) -->
                            <div class="border rounded-lg overflow-hidden">
                                <div class="bg-gray-100 px-3 py-2 flex justify-between items-center text-xs font-bold text-gray-700 uppercase">
                                    <span>Líneas de Compra / Insumos a Ingresar</span>
                                    <button type="button" wire:click="agregarLinea" class="text-red-700 hover:text-red-900 font-semibold text-xs">+ Agregar Ítem</button>
                                </div>
                                <div class="max-h-60 overflow-y-auto p-2 space-y-2">
                                    @foreach($items as $i => $item)
                                        <div class="grid grid-cols-12 gap-2 items-center bg-gray-50 p-2 rounded border text-xs">
                                            <div class="col-span-4">
                                                <input type="text" wire:model="items.{{ $i }}.descripcion" placeholder="Descripción en factura" class="w-full text-xs p-1 border-gray-300 rounded" />
                                            </div>
                                            <div class="col-span-3">
                                                <select wire:model="items.{{ $i }}.insumo_id" class="w-full text-xs p-1 border-gray-300 rounded">
                                                    <option value="">(Asociar Insumo)</option>
                                                    @foreach(Insumo::orderBy('nombre')->get() as $ins)
                                                        <option value="{{ $ins->id }}">{{ $ins->nombre }} (Stock: {{ (int)$ins->stock_actual }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-span-2">
                                                <input type="number" step="0.01" wire:model="items.{{ $i }}.cantidad" placeholder="Cant." class="w-full text-xs p-1 border-gray-300 rounded" title="Cantidad" />
                                            </div>
                                            <div class="col-span-2">
                                                <input type="number" step="0.01" wire:model="items.{{ $i }}.costo_unitario" placeholder="Costo Unit." class="w-full text-xs p-1 border-gray-300 rounded" title="Costo Unitario" />
                                            </div>
                                            <div class="col-span-1 text-center">
                                                <button type="button" wire:click="eliminarLinea({{ $i }})" class="text-red-600 hover:text-red-800 font-bold">&times;</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="bg-gray-100 px-4 py-2 flex justify-between items-center text-xs font-bold text-gray-800">
                                    <span>Total Compra a Debitar:</span>
                                    <span class="text-sm text-red-700">${{ number_format($this->calcularTotalPropuesto(), 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="flex justify-between items-center pt-3 border-t">
                                <button type="button" wire:click="$set('pasoActual', 1)" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs rounded">← Volver al OCR</button>
                                <button type="submit" class="px-5 py-2 bg-red-700 hover:bg-red-800 text-white rounded text-sm font-semibold">
                                    Confirmar Ingreso al Inventario y Descontar Presupuesto
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
