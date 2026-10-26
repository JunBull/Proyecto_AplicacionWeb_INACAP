<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0; url={{ auth()->check() ? route('dashboard') : route('login') }}">
    <title>INACAP Pañol - Redirección Institucional</title>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen font-sans">
    <script>
        window.location.href = "{{ auth()->check() ? route('dashboard') : route('login') }}";
    </script>
    <div class="text-center p-6">
        <p class="text-sm text-slate-600">Redirigiendo al portal institucional de pañol...</p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="text-xs text-red-700 font-semibold underline mt-2 inline-block">
            Haga clic aquí si no es redirigido automáticamente
        </a>
    </div>
</body>
</html>
