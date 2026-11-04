<?php

use App\Models\Asignatura;
use App\Models\Carrera;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $buscar = '';
    public string $filtroRol = 'todos';

    // Modal Crear / Editar
    public bool $mostrarModal = false;
    public ?int $usuarioId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $rol = 'docente';
    public bool $activo = true;
    public array $carrerasSeleccionadas = [];
    public array $asignaturasSeleccionadas = [];

    public function mount(): void
    {
        if (Auth::user()->rol !== 'administrador') {
            abort(403, 'Acceso restringido exclusivamente a Administradores.');
        }
    }

    public function usuarios()
    {
        $query = User::with(['carreras', 'asignaturas'])->orderBy('nombre');

        if (trim($this->buscar) !== '') {
            $t = '%' . trim($this->buscar) . '%';
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'LIKE', $t)
                  ->orWhere('email', 'LIKE', $t);
            });
        }

        if ($this->filtroRol !== 'todos') {
            $query->where('rol', $this->filtroRol);
        }

        return $query->get();
    }

    public function carrerasDisponibles()
    {
        return Carrera::where('activa', true)->orderBy('nombre')->get();
    }

    public function asignaturasDisponibles()
    {
        return Asignatura::with('carrera')->where('activo', true)->orderBy('nombre')->get();
    }

    public function abrirModalCrear(): void
    {
        $this->resetValidation();
        $this->usuarioId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->rol = 'docente';
        $this->activo = true;
        $this->carrerasSeleccionadas = [];
        $this->asignaturasSeleccionadas = [];
        $this->mostrarModal = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $this->resetValidation();
        $user = User::with(['carreras', 'asignaturas'])->findOrFail($id);
        $this->usuarioId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->rol = $user->rol;
        $this->activo = (bool) $user->activo;
        $this->carrerasSeleccionadas = $user->carreras->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->asignaturasSeleccionadas = $user->asignaturas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->mostrarModal = true;
    }

    public function guardar(): void
    {
        $rules = [
            'name' => 'required|string|max:150',
            'email' => [
                'required',
                'email',
                'max:150',
                $this->usuarioId ? Rule::unique('usuarios', 'email')->ignore($this->usuarioId) : Rule::unique('usuarios', 'email'),
            ],
            'rol' => 'required|in:administrador,encargado_panol,docente',
            'activo' => 'boolean',
        ];

        if (! $this->usuarioId) {
            $rules['password'] = 'required|string|min:8';
        } else {
            $rules['password'] = 'nullable|string|min:8';
        }

        $this->validate($rules, [
            'name.required' => 'El nombre del usuario es obligatorio.',
            'email.required' => 'El correo institucional es obligatorio.',
            'email.unique' => 'Este correo electrónico ya está registrado en el sistema.',
            'password.required' => 'La contraseña es obligatoria para nuevos usuarios.',
            'password.min' => 'La contraseña debe contener al menos 8 caracteres.',
        ]);

        if ($this->usuarioId) {
            $user = User::findOrFail($this->usuarioId);
            $user->name = $this->name;
            $user->nombre = $this->name;
            $user->email = $this->email;
            $user->rol = $this->rol;
            $user->activo = $this->activo;
            if (! empty($this->password)) {
                $user->password = Hash::make($this->password);
            }
            $user->save();
        } else {
            $user = User::create([
                'name' => $this->name,
                'nombre' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'rol' => $this->rol,
                'activo' => $this->activo,
            ]);
        }

        // Si el usuario es Docente, sincronizar carreras y asignaturas
        if ($this->rol === 'docente') {
            $user->carreras()->sync($this->carrerasSeleccionadas);
            $user->asignaturas()->sync($this->asignaturasSeleccionadas);
        } else {
            $user->carreras()->sync([]);
            $user->asignaturas()->sync([]);
        }

        $this->mostrarModal = false;
        session()->flash('mensaje', 'Usuario ' . ($this->usuarioId ? 'actualizado' : 'registrado') . ' exitosamente con sus carreras y asignaturas vinculadas.');
    }

    public function toggleActivo(int $id): void
    {
        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            session()->flash('error', 'No puede desactivar su propia cuenta de Administrador.');
            return;
        }
        $user->activo = ! $user->activo;
        $user->save();
        session()->flash('mensaje', "Estado del usuario '{$user->name}' actualizado correctamente.");
    }
}; ?>

<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Encabezado -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-red-100 text-red-800 border border-red-200">
                    Administración
                </span>
                <span class="text-xs text-slate-400 font-mono">v1.1</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 mt-1">Gestión de Usuarios y Roles</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Control centralizado de cuentas, roles del sistema y asignación de carreras y talleres a docentes (CP-23).</p>
        </div>
        <button wire:click="abrirModalCrear" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-700 hover:bg-red-800 text-white font-semibold text-xs sm:text-sm rounded-lg shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo Usuario
        </button>
    </div>

    @if(session()->has('mensaje'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-600 text-emerald-800 text-xs sm:text-sm rounded-r-lg">
            {{ session('mensaje') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div class="p-4 bg-red-50 border-l-4 border-red-600 text-red-800 text-xs sm:text-sm rounded-r-lg">
            {{ session('error') }}
        </div>
    @endif

    <!-- Filtros y Búsqueda -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
            <input wire:model.live.debounce.300ms="buscar" type="text" placeholder="Buscar por nombre o correo institucional..." class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600 pl-9">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <div class="w-full sm:w-48">
            <select wire:model.live="filtroRol" class="w-full text-xs sm:text-sm rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                <option value="todos">Todos los Roles</option>
                <option value="administrador">Administrador</option>
                <option value="encargado_panol">Encargado de Pañol</option>
                <option value="docente">Docente</option>
            </select>
        </div>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                <thead class="bg-slate-50 text-slate-700 font-semibold text-start">
                    <tr>
                        <th class="px-4 py-3 text-start">Nombre y Correo</th>
                        <th class="px-4 py-3 text-start">Rol Institucional</th>
                        <th class="px-4 py-3 text-start">Carreras y Asignaturas</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-800">
                    @forelse($this->usuarios() as $user)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $user->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $user->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($user->rol === 'administrador')
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase bg-purple-100 text-purple-800 border border-purple-200">Admin</span>
                                @elseif($user->rol === 'encargado_panol')
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">Pañolero</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200">Docente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                @if($user->rol === 'docente')
                                    <div class="space-y-1">
                                        <div class="text-[11px] text-slate-600">
                                            <span class="font-semibold text-slate-700">Carreras:</span>
                                            {{ $user->carreras->pluck('codigo')->implode(', ') ?: 'Sin asignar' }}
                                        </div>
                                        <div class="text-[11px] text-slate-600">
                                            <span class="font-semibold text-slate-700">Talleres:</span>
                                            {{ $user->asignaturas->pluck('codigo')->implode(', ') ?: 'Sin talleres asignados' }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-400 italic">Acceso institucional global</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleActivo({{ $user->id }})" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $user->activo ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-red-100 text-red-800 hover:bg-red-200' }}">
                                    {{ $user->activo ? 'Activo' : 'Inactivo' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <button wire:click="abrirModalEditar({{ $user->id }})" class="text-xs font-semibold text-red-700 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition">
                                    Editar / Asignar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                No se encontraron usuarios con los criterios ingresados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Crear / Editar Usuario con Asignación Docente -->
    @if($mostrarModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 space-y-4 border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-slate-900">
                        {{ $usuarioId ? 'Editar Usuario y Asignaciones' : 'Crear Nuevo Usuario Institucional' }}
                    </h2>
                    <button wire:click="$set('mostrarModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs sm:text-sm">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nombre Completo *</label>
                        <input wire:model="name" type="text" class="w-full rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                        @error('name') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Correo Institucional *</label>
                        <input wire:model="email" type="email" class="w-full rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                        @error('email') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            {{ $usuarioId ? 'Nueva Contraseña (dejar en blanco para conservar)' : 'Contraseña de Acceso *' }}
                        </label>
                        <input wire:model="password" type="password" class="w-full rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                        @error('password') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Rol en la Plataforma *</label>
                        <select wire:model.live="rol" class="w-full rounded-lg border-slate-300 focus:border-red-600 focus:ring-red-600">
                            <option value="docente">Docente</option>
                            <option value="encargado_panol">Encargado de Pañol</option>
                            <option value="administrador">Administrador</option>
                        </select>
                        @error('rol') <span class="text-[11px] text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if($rol === 'docente')
                    <!-- Asignación Académica Docente (CP-23, Sección 3.1) -->
                    <div class="border-t border-slate-100 pt-4 space-y-3">
                        <div class="font-bold text-slate-900 text-xs sm:text-sm flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-600"></span>
                            Asignación Académica (Carreras y Talleres Prácticos)
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 text-xs mb-1">Carreras Asociadas:</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-32 overflow-y-auto p-2 bg-slate-50 rounded-lg border border-slate-200">
                                @foreach($this->carrerasDisponibles() as $carrera)
                                    <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" wire:model="carrerasSeleccionadas" value="{{ $carrera->id }}" class="rounded text-red-600 focus:ring-red-600">
                                        <span>{{ $carrera->codigo }} - {{ $carrera->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 text-xs mb-1">Asignaturas / Talleres Habilitados:</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto p-2 bg-slate-50 rounded-lg border border-slate-200">
                                @forelse($this->asignaturasDisponibles() as $asig)
                                    <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" wire:model="asignaturasSeleccionadas" value="{{ $asig->id }}" class="rounded text-red-600 focus:ring-red-600">
                                        <span><strong>{{ $asig->codigo }}:</strong> {{ $asig->nombre }} ({{ $asig->carrera->codigo }})</span>
                                    </label>
                                @empty
                                    <div class="text-[11px] text-slate-400 col-span-2 italic">No hay asignaturas registradas. Puede crearlas en Carreras y Presupuesto.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-3">
                    <button wire:click="$set('mostrarModal', false)" type="button" class="px-4 py-2 text-xs sm:text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">
                        Cancelar
                    </button>
                    <button wire:click="guardar" type="button" class="px-5 py-2 text-xs sm:text-sm font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg shadow-sm">
                        {{ $usuarioId ? 'Guardar Cambios' : 'Crear Usuario' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
