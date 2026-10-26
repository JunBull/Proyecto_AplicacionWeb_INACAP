<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'INACAP Pañol') }} - Acceso Institucional</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-800 h-full">
        <div class="min-h-screen flex flex-col lg:flex-row">
            
            <!-- Panel Izquierdo: Identidad Institucional INACAP Temuco (Split-Screen) -->
            <div class="hidden lg:flex lg:w-1/2 xl:w-7/12 relative bg-gradient-to-br from-red-800 via-red-900 to-neutral-950 text-white p-12 xl:p-16 flex-col justify-between overflow-hidden shadow-2xl">
                <!-- Efecto visual de fondo: geometrías sutiles -->
                <div class="absolute inset-0 opacity-10 pointer-events-none">
                    <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="currentColor" stroke-width="1" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                    </svg>
                </div>

                <!-- Cabecera del panel lateral -->
                <div class="relative z-10">
                    <div class="inline-flex items-center gap-3 bg-white/10 backdrop-blur-md px-4 py-2 rounded-full border border-white/15">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs font-semibold tracking-wider uppercase text-red-100">Portal de Pañol Activo</span>
                    </div>
                </div>

                <!-- Contenido central hero -->
                <div class="relative z-10 max-w-xl my-auto py-8">
                    <div class="mb-6">
                        <x-application-logo class="h-16 w-auto text-white fill-current drop-shadow-md" />
                    </div>

                    <h1 class="text-3xl xl:text-4xl font-extrabold tracking-tight text-white leading-tight">
                        Sistema Web de Gestión de Pañol e Inventario
                    </h1>
                    <p class="mt-4 text-base xl:text-lg text-red-100/90 leading-relaxed font-normal">
                        Área de Informática y Telecomunicaciones &bull; Sede Temuco. Control centralizado de equipos, consumibles, solicitudes de docencia y trazabilidad de activos.
                    </p>

                    <!-- Píldoras de roles autorizados -->
                    <div class="mt-8 pt-6 border-t border-white/15 grid grid-cols-3 gap-3">
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3.5 border border-white/10">
                            <div class="text-xs uppercase tracking-wider text-red-200 font-bold">Administrador</div>
                            <div class="text-[11px] text-white/80 mt-1 leading-snug">Presupuesto, compras, usuarios y directivas.</div>
                        </div>
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3.5 border border-white/10">
                            <div class="text-xs uppercase tracking-wider text-red-200 font-bold">Pañolero</div>
                            <div class="text-[11px] text-white/80 mt-1 leading-snug">Despachos, escaneo de barras, OCR y mantenimiento.</div>
                        </div>
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3.5 border border-white/10">
                            <div class="text-xs uppercase tracking-wider text-red-200 font-bold">Docente</div>
                            <div class="text-[11px] text-white/80 mt-1 leading-snug">Solicitudes anticipadas y catálogo en tiempo real.</div>
                        </div>
                    </div>
                </div>

                <!-- Pie de página del panel lateral con aclaración institucional -->
                <div class="relative z-10 text-xs text-red-200/80 border-t border-white/10 pt-4 flex items-center justify-between">
                    <span>INACAP Temuco &bull; Av. Luis Durand 02150</span>
                    <span class="inline-flex items-center gap-1.5 bg-black/25 px-2.5 py-1 rounded text-[11px] text-white/90">
                        <svg class="w-3.5 h-3.5 text-amber-300 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        Estudiantes no requieren cuenta
                    </span>
                </div>
            </div>

            <!-- Panel Derecho: Formulario de Autenticación -->
            <div class="flex-1 lg:w-1/2 xl:w-5/12 flex flex-col justify-center items-center p-6 sm:p-12 lg:p-16 bg-slate-50 min-h-screen">
                <!-- Logotipo visible en vista móvil y tablet -->
                <div class="lg:hidden mb-6 text-center">
                    <x-application-logo class="h-12 w-auto mx-auto text-slate-800" />
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest mt-2">Área Informática &bull; Sede Temuco</p>
                </div>

                <div class="w-full max-w-md bg-white p-8 sm:p-10 rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-200/80">
                    {{ $slot }}
                </div>

                <div class="mt-6 text-center text-xs text-slate-400">
                    &copy; {{ date('Y') }} INACAP Temuco &bull; Todos los derechos reservados
                </div>
            </div>

        </div>
    </body>
</html>
