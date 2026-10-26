<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">
            Recuperar Contraseña
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            Ingresa tu correo institucional registrado y te enviaremos un enlace seguro para restablecerla.
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo Institucional" class="text-xs font-bold uppercase tracking-wider text-slate-700" />
            <x-text-input 
                wire:model="email" 
                id="email" 
                class="block w-full mt-1 rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 sm:text-sm pl-3 pr-3 py-2.5" 
                type="email" 
                name="email" 
                placeholder="usuario@inacap.cl"
                required 
                autofocus 
            />
            <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs" />
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3">
            <a href="{{ route('login') }}" class="text-xs text-slate-600 hover:text-red-700 font-medium transition" wire:navigate>
                &larr; Volver al inicio de sesión
            </a>

            <x-primary-button class="py-2.5 px-4 text-xs font-semibold">
                Enviar Enlace de Recuperación
            </x-primary-button>
        </div>
    </form>
</div>
