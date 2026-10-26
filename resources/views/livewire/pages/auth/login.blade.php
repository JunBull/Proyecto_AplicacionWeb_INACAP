<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Encabezado del Formulario -->
    <div class="mb-6 text-start">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">
            Acceso Institucional
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            Ingresa con tu correo institucional y contraseña asignada.
        </p>
    </div>

    <!-- Estado de la Sesión -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <!-- Correo Electrónico Institucional -->
        <div>
            <x-input-label for="email" value="Correo Institucional" class="text-xs font-bold uppercase tracking-wider text-slate-700" />
            <div class="relative mt-1">
                <x-text-input 
                    wire:model="form.email" 
                    id="email" 
                    class="block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 sm:text-sm pl-3 pr-10 py-2.5" 
                    type="email" 
                    name="email" 
                    placeholder="usuario@inacap.cl"
                    required 
                    autofocus 
                    autocomplete="username" 
                />
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                    </svg>
                </div>
            </div>
            <x-input-error :messages="$errors->get('form.email')" class="mt-1 text-xs" />
        </div>

        <!-- Contraseña -->
        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Contraseña" class="text-xs font-bold uppercase tracking-wider text-slate-700" />
                @if (Route::has('password.request'))
                    <a class="text-xs text-red-700 hover:text-red-800 font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-red-500 rounded" href="{{ route('password.request') }}" wire:navigate>
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>

            <div class="relative mt-1">
                <x-text-input 
                    wire:model="form.password" 
                    id="password" 
                    class="block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 sm:text-sm pl-3 pr-10 py-2.5"
                    type="password"
                    name="password"
                    placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                    required 
                    autocomplete="current-password" 
                />
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-1 text-xs" />
        </div>

        <!-- Recordar Sesión -->
        <div class="flex items-center justify-between pt-1">
            <label for="remember" class="inline-flex items-center cursor-pointer">
                <input 
                    wire:model="form.remember" 
                    id="remember" 
                    type="checkbox" 
                    class="rounded border-slate-300 text-red-700 shadow-sm focus:ring-red-600 h-4 w-4" 
                    name="remember"
                >
                <span class="ms-2 text-xs text-slate-600 font-medium select-none">Recordar mi sesión</span>
            </label>
        </div>

        <!-- Botón de Ingreso con Feedback wire:loading -->
        <div class="pt-2">
            <x-primary-button class="w-full justify-center py-2.5 px-4 text-sm font-semibold rounded-lg shadow-md shadow-red-700/20">
                Iniciar Sesión
            </x-primary-button>
        </div>
    </form>

    <!-- Nota Informativa de Estudiantes (Sin Cuenta) -->
    <div class="mt-6 pt-4 border-t border-slate-100 bg-slate-50/70 -mx-4 -mb-4 px-4 py-3 rounded-b-2xl">
        <div class="flex items-start gap-2.5">
            <svg class="w-4 h-4 text-slate-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-[11px] text-slate-600 leading-relaxed">
                <span class="font-semibold text-slate-700">Atención estudiantes:</span> El sistema es exclusivo para funcionarios y docentes. Los préstamos de materiales se gestionan presentando tu credencial o carnet en el mesón de pañol.
            </p>
        </div>
    </div>
</div>
