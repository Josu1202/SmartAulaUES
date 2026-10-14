<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171717">
    <title>Iniciar sesión · {{ config('smartaulaues.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ config('smartaulaues.version') }}" type="image/svg+xml">@vite('resources/js/app.js')
</head>

<body class="login-page">
    <main class="login-shell">
        <section class="login-story"><x-logo />
            <div class="login-story-copy"><span class="eyebrow">CONECTAMOS CADA CLASE</span>
                <h1>La asistencia.<br>Más simple.<br><span>Más conectada.</span></h1>
                <p>Un espacio para organizar tus clases y acompañar cada paso de la vida universitaria.</p>
                <div class="login-methods"><span><x-icon name="nfc" />NFC</span><span><x-icon
                            name="checklist" />Asistencia</span><span><x-icon name="chart" />Seguimiento</span></div>
            </div><small>Asistencia académica universitaria</small>
            <div class="story-orbit" aria-hidden="true"></div>
        </section>
        <section class="login-form-area">
            <div class="login-form-wrap"><span
                    class="demo-pill">{{ config('smartaulaues.demo_enabled') ? 'Entorno de demostración' : 'Portal universitario' }}</span>
                <h2>Bienvenido a {{ config('smartaulaues.name') }}</h2>
                <p class="text-secondary mb-4">Inicia sesión con tu cuenta asignada.</p>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
                <form method="POST" action="{{ route('login') }}">@csrf
                    <div class="mb-3"><label class="form-label" for="email">Correo electrónico</label><input
                            class="form-control form-control-lg @error('email') is-invalid @enderror" id="email"
                            type="email" name="email" value="{{ old('email') }}"
                            placeholder="tu.correo@universidad.edu.sv" autocomplete="username" required autofocus></div>
                    <div class="mb-3"><label class="form-label" for="password">Contraseña</label>
                        <div class="password-field"><input class="form-control form-control-lg" id="password"
                                type="password" name="password" autocomplete="current-password" required><button
                                type="button" data-password-toggle aria-controls="password"
                                aria-label="Mostrar contraseña" aria-pressed="false"><x-icon name="eye" /></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 login-submit">Iniciar sesión<x-icon
                            name="arrow" /></button>
                </form>
                <div class="login-help"><x-icon name="lock" />
                    <p>Si necesitas una cuenta o ayuda con tu acceso, consulta a coordinación.</p>
                </div>
                <small class="login-version">{{ config('smartaulaues.name') }} · v{{ config('smartaulaues.version') }}</small>
            </div>
        </section>
    </main>
</body>

</html>
