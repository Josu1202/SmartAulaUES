<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#171717">
    <title>@yield('title', 'Inicio') · {{ config('smartaulaues.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ config('smartaulaues.version') }}" type="image/svg+xml">@vite('resources/js/app.js')
</head>

<body>
    <a class="skip-link" href="#main">Ir al contenido</a>
    <x-sidebar />
    <button class="sidebar-overlay" data-close-menu aria-label="Cerrar menú" tabindex="-1"></button>
    <div class="smartaulaues-main">
        <header class="smartaulaues-topbar">
            <div class="d-flex align-items-center gap-3"><button class="menu-toggle" type="button"
                    aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menú" data-menu-toggle><x-icon
                        name="menu" /></button><span class="topbar-crumb">Espacio
                    académico<span>/</span><strong>@yield('title', 'Inicio')</strong></span></div>
            <div class="d-flex align-items-center gap-3"><span
                    class="demo-pill">{{ config('smartaulaues.demo_enabled') ? 'Demostración' : 'Campus' }}</span>
                <div class="dropdown"><button class="user-menu" type="button"
                        aria-label="Menú de {{ auth()->user()->name }}" data-bs-toggle="dropdown"
                        aria-expanded="false"><span
                            class="user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span
                            class="user-menu-text">{{ auth()->user()->name }}<small>{{ auth()->user()->role->label() }}</small></span></button>
                    <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item"
                            href="{{ route('profile') }}"><x-icon name="user" class="me-2" />Mi cuenta</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"
                                type="submit"><x-icon name="logout" class="me-2" />Cerrar sesión</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="smartaulaues-content" id="main" tabindex="-1">
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @yield('content')
        </main>
        <footer class="smartaulaues-footer"><span>Asistencia académica universitaria</span><span>{{ config('smartaulaues.name') }} ·
                v{{ config('smartaulaues.version') }}</span></footer>
    </div>
</body>

</html>
