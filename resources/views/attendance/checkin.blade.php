<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="referrer" content="no-referrer">
    <title>Registrar asistencia · SmartAulaUES</title><link rel="icon" href="{{ asset('favicon.svg') }}">@vite('resources/js/app.js')
</head>
<body>
<main class="checkin-shell" data-checkin data-portal="{{ $portal->id }}"
      data-checkin-expires="{{ ($portal->closed_at || $portal->session->state !== \App\Enums\SessionState::InProgress ? now() : min($portal->expires_at, $portal->session->ends_at))->toIso8601String() }}"
      data-geo-required="{{ auth()->check() && $portal->session->effectiveAttendanceModality() === 'in_person' ? 'true' : 'false' }}"
      @include('attendance.geolocation-options') data-method="{{ $method }}"
      data-login-url="{{ route('login') }}" data-submit-url="{{ route('checkin.store', $portal) }}">
    <div class="card"><div class="card-body">
        <x-logo/><h1>Registrar mi asistencia</h1>
        @auth
            <p class="text-secondary">{{ $portal->session->offering->subject->name }} · {{ $portal->session->starts_at->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}</p>
            <p>Cuenta: <strong>{{ auth()->user()->name }}</strong></p>
            @if(auth()->user()->must_change_password)
                <p class="alert alert-warning">Cambia tu contraseña provisional antes de registrar asistencia. Después vuelve a abrir la etiqueta, el QR o el enlace vigente.</p>
                <a class="btn btn-primary" href="{{ route('profile') }}">Cambiar contraseña</a>
            @elseif(!$portal->session->offering->active)
                <p class="alert alert-warning" role="status">Grupo desactivado. Se conserva la consulta, pero no se admite nueva actividad académica.</p>
                <a class="btn btn-outline-dark" href="{{ route('offerings.show', $portal->session->offering) }}">Consultar grupo</a>
            @else
            @if($method === 'nfc_tag')<p class="alert alert-info">Acceso mediante etiqueta compartida. El servidor verifica tu cuenta, inscripción, ventana y ubicación; una URL copiada no demuestra una lectura NFC física.</p>@endif
            @if($portal->session->effectiveAttendanceModality() === 'in_person')
                <p>Esta clase es presencial. Al pulsar Registrar asistencia se solicitará tu ubicación. La distancia a la referencia del docente más ambas incertidumbres debe ser ≤ {{ config('geolocation.radius_m') }} m. Valida proximidad aproximada al docente; no acredita presencia dentro del aula. Solo se usa para este intento; no se realiza seguimiento.</p>
                @include('attendance.location-acquisition')
            @endif
            <button class="btn btn-primary w-100" type="button" data-confirm-checkin>Registrar asistencia</button>
            <p class="mt-3" role="status" data-geo-alternative hidden>No hemos podido validar tu ubicación. Intenta acercarte al docente o solicita registrar tu asistencia por NFC o de forma manual.</p>
            @endif
        @else
            <p class="text-secondary">Inicia sesión con tu cuenta de estudiante para continuar con el enlace de esta clase.</p>
            <form method="POST" action="{{ route('login') }}" data-checkin-login><input type="hidden" name="_form" value="{{ hash('sha256', 'attendance/checkin.blade.php:1'.'|'.(route('login'))) }}">
                @csrf
                <x-form-field :scope="hash('sha256', 'attendance/checkin.blade.php:1'.'|'.(route('login')))" name="email" label="Correo de estudiante" type="email" required autocomplete="username"/>
                <x-form-field :scope="hash('sha256', 'attendance/checkin.blade.php:1'.'|'.(route('login')))" name="password" label="Contraseña" type="password" required autocomplete="current-password"/>
                <button class="btn btn-primary w-100">Iniciar sesión y continuar</button>
            </form>
        @endauth
        <div class="live-status mt-4" role="status" aria-live="polite" data-checkin-status>La asistencia se confirma únicamente después de la respuesta del servidor.</div>
        <p class="form-hint mt-3 mb-0">Este enlace tiene vigencia limitada y solo registra tu propia inscripción.</p>
        @auth<a class="back-link mt-3" href="{{ route('dashboard') }}">Volver a mi espacio</a>@endauth
    </div></div>
</main>
</body>
</html>
