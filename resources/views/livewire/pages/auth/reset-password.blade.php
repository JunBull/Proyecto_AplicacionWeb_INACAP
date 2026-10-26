<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">
            Establecer Nueva Contraseña
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            Define una contraseña segura para tu cuenta institucional.
        </p>
    </div>

    <form wire:submit="resetPassword" class="space-y-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo Institucional" class="text-xs font-bold uppercase tracking-wider text-slate-700" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 sm:text-sm pl-3 pr-3 py-2.5" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" value="Nueva Contraseña" class="text-xs font-bold uppercase tracking-wider text-slate-700" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 sm:text-sm pl-3 pr-3 py-2.5" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" value="Confirmar Nueva Contraseña" class="text-xs font-bold uppercase tracking-wider text-slate-700" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 sm:text-sm pl-3 pr-3 py-2.5"
                          type="password"
                          name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-xs" />
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center py-2.5 px-4 text-sm font-semibold">
                Actualizar Contraseña
            </x-primary-button>
        </div>
    </form>
</div>
