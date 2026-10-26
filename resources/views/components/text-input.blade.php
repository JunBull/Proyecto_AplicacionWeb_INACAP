@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 focus:border-red-600 focus:ring-red-600 rounded-lg shadow-sm text-slate-900 placeholder-slate-400 text-sm transition']) }}>
