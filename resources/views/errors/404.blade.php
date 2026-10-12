<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página no encontrada · SIAA</title>@vite('resources/js/app.js')
</head>

<body class="error-page">
    <main class="card empty-panel"><x-logo /><span class="error-code">404</span>
        <h1>Página no encontrada</h1>
        <p>La dirección solicitada no está disponible.</p><a class="btn btn-primary" href="{{ url('/') }}">Ir al
            inicio</a>
    </main>
</body>

</html>
