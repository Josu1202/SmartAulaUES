<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No pudimos completar la solicitud · {{ config('smartaulaues.name') }}</title>@vite('resources/js/app.js')
</head>

<body class="error-page">
    <main class="card empty-panel"><x-logo /><span class="error-code">500</span>
        <h1>No pudimos completar la solicitud</h1>
        <p>Inténtalo nuevamente. Si el problema continúa, consulta a coordinación.</p><a class="btn btn-primary"
            href="{{ url('/') }}">Ir al inicio</a>
    </main>
</body>

</html>
