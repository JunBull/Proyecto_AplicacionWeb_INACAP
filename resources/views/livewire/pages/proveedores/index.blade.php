<?php

use App\Models\Proveedor;
use App\Services\ProveedorService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $buscar = '';
    public string $filtroTipo = '';
    public bool $soloActivos = false;

    // Modal nuevo proveedor
    public bool $mostrarModalCrear = false;
    public string $razon_social = '';
    public string $rut = '';
    public string $correo = '';
    public string $telefono = '';
    public string $tipo = 'insumos';
    public bool $activo = true;

    public function proveedores(ProveedorService $service)
    {
        $query = Proveedor::query();

        if (trim($this->buscar) !== '') {
            $t = '%' . trim($this->buscar) . '%';
            $query->where(function ($q) use ($t) {
                $q->where('razon_social', 'LIKE', $t)
                  ->orWhere('rut', 'LIKE', $t)
                  ->orWhere('correo', 'LIKE', $t);
            });
        }

        if ($this->filtroTipo !== '') {
            if ($this->filtroTipo === 'insumos') {
                $query->whereIn('tipo', ['insumos', 'ambos']);
            } elseif ($this->filtroTipo === 'mantenimiento') {
                $query->whereIn('tipo', ['mantenimiento', 'ambos']);
            } else {
                $query->where('tipo', $this->filtroTipo);
            }
        }

        if ($this->soloActivos) {
            $query->where('activo', true);
        }

        return $query->orderBy('razon_social')->paginate(15);
    }

    public function guardarProveedor(ProveedorService $service): void
    {
        try {
            $service->registrarProveedor([
                'razon_social' => $this->razon_social,
                'rut' => $this->rut,
                'correo' => $this->correo,
                'telefono' => $this->telefono ?: null,
                'tipo' => $this->tipo,
                'activo' => $this->activo,
            ]);

            $this->reset(['razon_social', 'rut', 'correo', 'telefono', 'tipo', 'activo', 'mostrarModalCrear']);
            $this->activo = true;
            $this->tipo = 'insumos';
            session()->flash('mensaje', 'Proveedor registrado exitosamente en el catálogo categorizado (CP-16).');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function alternarEstado(int $id, ProveedorService $service): void
    {
        $proveedor = Proveedor::findOrFail($id);
        $service->toggleActivo($proveedor);
        session()->flash('mensaje', "Estado del proveedor {$proveedor->razon_social} actualizado.");
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
                    <h2 class="text-xl font-bold text-gray-800">Directorio de Proveedores Categorizados</h2>
                    <p class="text-sm text-gray-500">Gestión clasificada de proveedores de insumos y mantenimiento (CP-16)</p>
                </div>
                <button
                    wire:click="$set('mostrarModalCrear', true)"
                    class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-md shadow focus:outline-none"
                >
                    + Registrar Proveedor
                </button>
            </div>

            <!-- Filtros -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Buscar</label>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="buscar"
                        placeholder="Razón social, RUT o correo..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-red-500 focus:ring-red-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Rubro Comercial</label>
                    <select
                        wire:model.live="filtroTipo"
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-red-500 focus:ring-red-500"
                    >
                        <option value="">Todos los rubros</option>
                        <option value="insumos">Insumos y Materiales</option>
                        <option value="mantenimiento">Mantenimiento y Reparación</option>
                        <option value="ambos">Ambos Rubros</option>
                    </select>
                </div>
                <div class="flex items-center pt-5">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="soloActivos" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                        <span class="ml-2 text-sm text-gray-700">Ver solo proveedores activos</span>
                    </label>
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-700 font-semibold uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left">Razón Social</th>
                            <th class="px-4 py-3 text-left">RUT</th>
                            <th class="px-4 py-3 text-left">Contacto / Correo</th>
                            <th class="px-4 py-3 text-left">Rubro</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($this->proveedores(app(\App\Services\ProveedorService::class)) as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $p->razon_social }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $p->rut }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    <div>{{ $p->correo }}</div>
                                    @if($p->telefono)
                                        <div class="text-xs text-gray-400">{{ $p->telefono }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($p->tipo === 'insumos')
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Insumos</span>
                                    @elseif($p->tipo === 'mantenimiento')
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">Mantenimiento</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-teal-100 text-teal-800">Insumos & Mant.</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($p->activo)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Activo</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        wire:click="alternarEstado({{ $p->id }})"
                                        class="text-xs text-gray-600 hover:text-red-700 font-medium underline"
                                    >
                                        {{ $p->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                    No se encontraron proveedores que coincidan con la búsqueda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Crear Proveedor -->
        @if($mostrarModalCrear)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-2">
                        <h3 class="text-lg font-bold text-gray-800">Registrar Nuevo Proveedor</h3>
                        <button wire:click="$set('mostrarModalCrear', false)" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    <form wire:submit.prevent="guardarProveedor" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Razón Social *</label>
                            <input type="text" wire:model="razon_social" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Ej. Distribuidora Electrónica Austral SpA" />
                            @error('razon_social') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase">RUT *</label>
                                <input type="text" wire:model="rut" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Ej. 76.123.456-7" />
                                @error('rut') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase">Teléfono</label>
                                <input type="text" wire:model="telefono" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="+56 9 8765 4321" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Correo Electrónico * (para cotizaciones)</label>
                            <input type="email" wire:model="correo" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="ventas@proveedoraustral.cl" />
                            @error('correo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase">Rubro Comercial *</label>
                                <select wire:model="tipo" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="insumos">Insumos y Materiales</option>
                                    <option value="mantenimiento">Mantenimiento y Reparación</option>
                                    <option value="ambos">Ambos Rubros</option>
                                </select>
                                @error('tipo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex items-center pt-5">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="activo" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                                    <span class="ml-2 text-sm text-gray-700">Proveedor habilitado</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-2 pt-4 border-t">
                            <button
                                type="button"
                                wire:click="$set('mostrarModalCrear', false)"
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-sm"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white rounded-md text-sm font-semibold"
                            >
                                Guardar Proveedor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
