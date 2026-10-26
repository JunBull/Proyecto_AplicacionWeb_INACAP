<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 · Incidencia Técnica - Pañol INACAP</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans text-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg border border-gray-200 p-8 text-center" role="alert" aria-labelledby="error-title">
        <div class="w-16 h-16 bg-red-100 text-red-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <span class="inline-block px-3 py-1 bg-red-50 text-red-700 text-xs font-semibold rounded-full uppercase tracking-wider mb-2">Error 500 · Servidor</span>
        <h1 id="error-title" class="text-2xl font-bold text-gray-900 mb-2">Incidencia Técnica Controlada</h1>
        <p class="text-sm text-gray-600 mb-6">
            Ocurrió una eventualidad al procesar la operación. El evento ha sido registrado de forma segura en las bitácoras del servidor para su auditoría técnica sin divulgación de información interna.
        </p>
        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center w-full px-5 py-2.5 bg-red-700 hover:bg-red-800 text-white font-medium text-sm rounded-lg transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600">
            Regresar al Panel Principal
        </a>
    </div>
</body>
</html>
