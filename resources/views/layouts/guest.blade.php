<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'INACAP Pañol') }} - Acceso Institucional</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-inacap.svg') }}">

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

                <!-- Contenido central hero -->
                <div class="relative z-10 max-w-xl my-auto py-8">
                    <div class="mb-6">
                        <x-application-logo class="h-14 w-auto text-white fill-current drop-shadow-md" monochrome />
                    </div>

                    <h1 class="text-3xl xl:text-4xl font-extrabold tracking-tight text-white leading-tight">
                        Sistema Web de Gestión de Pañol e Inventario
                    </h1>
                </div>

                <!-- Pie de página del panel lateral con aclaración institucional -->
                <div class="relative z-10 text-xs text-red-200/80 border-t border-white/10 pt-4 flex items-center">
                    <span>INACAP Temuco &bull; Av. Luis Durand 02150</span>
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
