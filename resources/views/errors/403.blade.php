<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso restringido · SIAA</title>@vite('resources/js/app.js')
</head>

<body class="error-page">
    <main class="card empty-panel"><x-logo /><span class="error-code">403</span>
        <h1>Acceso restringido</h1>
        <p>Tu cuenta no tiene permiso para acceder a este recurso.</p><a class="btn btn-primary"
            href="{{ url('/') }}">Ir al inicio</a>
    </main>
</body>

</html>
