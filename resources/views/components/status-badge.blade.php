@props(['estado', 'label' => null])

@php
$estadoNorm = strtolower(trim((string) $estado));

// Mapeo accesible con contraste WCAG 2.2 AA (ratio >= 4.5:1)
$config = match($estadoNorm) {
    'operativo', 'disponible', 'aprobada', 'aprobado', 'activo', 'bueno' => [
        'bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'icon' => 'check',
        'text' => $label ?? ucfirst(str_replace('_', ' ', $estadoNorm)),
    ],
    'mantenimiento', 'en_revision', 'en_mantenimiento', 'pendiente', 'en_espera' => [
        'bg' => 'bg-amber-50 text-amber-900 border-amber-300',
        'icon' => 'clock',
        'text' => $label ?? ucfirst(str_replace('_', ' ', $estadoNorm)),
    ],
    'stock_critico', 'critico', 'falla', 'rechazada', 'rechazado', 'danado', 'urgente' => [
        'bg' => 'bg-red-50 text-red-800 border-red-300',
        'icon' => 'alert',
        'text' => $label ?? ($estadoNorm === 'stock_critico' ? 'Stock Crítico' : ucfirst(str_replace('_', ' ', $estadoNorm))),
    ],
    'baja', 'dado_de_baja', 'cerrada', 'cerrado', 'no_disponible', 'inactivo', 'desechado' => [
        'bg' => 'bg-slate-100 text-slate-800 border-slate-300',
        'icon' => 'ban',
        'text' => $label ?? ucfirst(str_replace('_', ' ', $estadoNorm)),
    ],
    default => [
        'bg' => 'bg-slate-50 text-slate-700 border-slate-200',
        'icon' => 'info',
        'text' => $label ?? ucfirst(str_replace('_', ' ', $estadoNorm)),
    ]
};
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border ' . $config['bg']]) }}>
    @if($config['icon'] === 'check')
        <svg class="w-3.5 h-3.5 shrink-0 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
    @elseif($config['icon'] === 'clock')
        <svg class="w-3.5 h-3.5 shrink-0 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    @elseif($config['icon'] === 'alert')
        <svg class="w-3.5 h-3.5 shrink-0 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
    @elseif($config['icon'] === 'ban')
        <svg class="w-3.5 h-3.5 shrink-0 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
        </svg>
    @else
        <svg class="w-3.5 h-3.5 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    @endif
    <span>{{ $config['text'] }}</span>
</span>
