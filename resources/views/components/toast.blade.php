@props([
    'tipo' => 'success',
    'mensaje' => null,
    'tiempo' => 4000,
])

@php
$flashMensaje = $mensaje ?? session('mensaje') ?? session('success') ?? session('status');
$flashError = session('error');
$flashInfo = session('info') ?? session('info_item');

$activo = $flashMensaje || $flashError || $flashInfo;
$tipoEfectivo = $flashError ? 'error' : ($flashInfo ? 'info' : 'success');
$textoEfectivo = $flashError ?: ($flashInfo ?: $flashMensaje);
@endphp

@if($activo)
<div 
    x-data="{ show: true }" 
    x-init="setTimeout(() => show = false, {{ $tiempo }})"
    x-show="show" 
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:translate-x-4 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0 scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="fixed bottom-5 right-5 z-50 max-w-md w-full shadow-2xl rounded-2xl border p-4 bg-white flex items-start gap-3.5 
        {{ $tipoEfectivo === 'success' ? 'border-emerald-200 text-slate-800' : '' }}
        {{ $tipoEfectivo === 'error' ? 'border-red-200 text-slate-800' : '' }}
        {{ $tipoEfectivo === 'info' ? 'border-sky-200 text-slate-800' : '' }}"
    role="alert"
    aria-live="assertive"
>
    <!-- Icono distintivo -->
    <div class="shrink-0 mt-0.5">
        @if($tipoEfectivo === 'success')
            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>
        @elseif($tipoEfectivo === 'error')
            <div class="w-8 h-8 rounded-full bg-red-100 text-red-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
        @else
            <div class="w-8 h-8 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        @endif
    </div>

    <!-- Contenido del mensaje -->
    <div class="flex-1 min-w-0">
        <div class="text-xs font-bold uppercase tracking-wider 
            {{ $tipoEfectivo === 'success' ? 'text-emerald-800' : '' }}
            {{ $tipoEfectivo === 'error' ? 'text-red-800' : '' }}
            {{ $tipoEfectivo === 'info' ? 'text-sky-800' : '' }}">
            {{ $tipoEfectivo === 'success' ? 'Operación Exitosa' : ($tipoEfectivo === 'error' ? 'Atención Requerida' : 'Notificación') }}
        </div>
        <div class="text-sm text-slate-700 mt-0.5 leading-snug">
            {{ $textoEfectivo }}
        </div>
    </div>

    <!-- Botón de Cerrar -->
    <button 
        @click="show = false" 
        class="shrink-0 text-slate-400 hover:text-slate-600 rounded-lg p-1 transition focus:outline-none focus:ring-2 focus:ring-slate-300"
        aria-label="Cerrar notificación"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
@endif
