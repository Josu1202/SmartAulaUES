<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>La sesión ha vencido · {{ config('smartaulaues.name') }}</title>@vite('resources/js/app.js')
</head>

<body class="error-page">
    <main class="card empty-panel"><x-logo /><span class="error-code">419</span>
        <h1>La sesión ha vencido</h1>
        <p>Vuelve a iniciar sesión e intenta nuevamente.</p><a class="btn btn-primary" href="{{ url('/') }}">Ir al
            inicio</a>
    </main>
</body>

</html>
