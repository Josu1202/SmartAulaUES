@extends('layouts.app')
@section('title', $user->exists ? 'Editar cuenta' : 'Nueva cuenta')
@section('content')
    <a class="back-link" href="{{ route($prefix.'.index') }}">← {{ $user->role === \App\Enums\Role::Student ? 'Estudiantes' : ($user->role === \App\Enums\Role::Teacher ? 'Docentes' : 'Coordinación') }}</a>
    <x-page-heading :title="$user->exists ? 'Editar cuenta' : 'Nueva cuenta'" subtitle="Asigna un perfil y controla el acceso al espacio académico." eyebrow="ADMINISTRACIÓN" />
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <form method="POST" action="{{ $user->exists ? route($prefix.'.update', $user) : route($prefix.'.store') }}">
                    @csrf @if($user->exists) @method('PUT') @endif
                    <div class="row"><div class="col-md-6"><x-form-field name="name" label="Nombre completo" :value="$user->name" required maxlength="255" autocomplete="name" /></div>
                    <div class="col-md-6"><x-form-field name="email" label="Correo electrónico" type="email" :value="$user->email" required maxlength="255" autocomplete="email" /></div></div>
                    <div class="row"><div class="col-md-6"><x-form-field name="role" label="Perfil" :value="$user->role->value" :options="collect($roles)->mapWithKeys(fn($role) => [$role->value => $role->label()])" required help="Una cuenta tiene un único perfil. El historial limita los cambios de perfil." /></div>
                    <div class="col-md-6"><x-form-field name="student_code" label="Carné del estudiante" :value="$user->student_code" maxlength="30" help="Obligatorio para estudiantes; vacío para los otros perfiles." /></div></div>
                    @if($user->role === \App\Enums\Role::Student)
                        <div class="row"><div class="col-md-6"><x-form-field name="entry_year" label="Año de ingreso" type="number" :value="$user->entry_year" required min="1900" max="2200" help="Por ejemplo: 2026. Los registros antiguos sin año se conservan." /></div><div class="col-md-6"><x-form-field name="study_year" label="Año de carrera" :value="$user->study_year" :options="['' => 'Selecciona un año'] + collect(range(1,10))->mapWithKeys(fn($year) => [$year => $year.'º año'])->all()" required /></div></div>
                    @endif
                    <x-form-field name="active" label="Estado de la cuenta" :value="(int) $user->active" :options="[1 => 'Activa', 0 => 'Inactiva']" required help="Desactivar conserva el historial y bloquea el inicio de sesión." />
                    @if(!$user->exists)
                        <div class="row"><div class="col-md-6"><x-form-field name="password" label="Contraseña inicial" type="password" required minlength="12" maxlength="128" autocomplete="new-password" help="Al menos 12 caracteres. Entrégala al titular de forma privada." /></div>
                        <div class="col-md-6"><x-form-field name="password_confirmation" label="Confirmar contraseña" type="password" required minlength="12" autocomplete="new-password" /></div></div>
                    @else
                        <x-form-field name="reason" label="Motivo del cambio" type="textarea" maxlength="1000" help="Obligatorio al cambiar el perfil o el estado de la cuenta." />
                    @endif
                    <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $user->exists ? 'Guardar cambios' : 'Crear cuenta' }}</button><a class="btn btn-outline-secondary" href="{{ route($prefix.'.index') }}">Cancelar</a></div>
                </form>
            </div></div>
            @if($user->exists)
                <div class="card mt-4"><div class="card-header"><h2 class="card-title">Restablecer acceso</h2></div><div class="card-body">
                    <p class="text-secondary">La nueva contraseña revoca las sesiones anteriores. Comunícala al titular de forma privada.</p>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}">@csrf @method('PUT')
                        <div class="row"><div class="col-md-6"><x-form-field name="password" id="reset-password" label="Nueva contraseña" type="password" required minlength="12" maxlength="128" autocomplete="new-password" /></div><div class="col-md-6"><x-form-field name="password_confirmation" id="reset-password-confirmation" label="Confirmar contraseña" type="password" required autocomplete="new-password" /></div></div>
                        <x-form-field name="reason" id="reset-reason" label="Motivo del restablecimiento" required minlength="5" maxlength="1000" />
                        <button class="btn btn-outline-dark" type="submit">Restablecer contraseña</button>
                    </form>
                </div></div>
            @endif
        </div>
        <div class="col-lg-4"><aside class="card"><div class="card-body"><span class="eyebrow">ACCESO CONTROLADO</span><h2 class="h3">Cada cuenta, un espacio</h2><p class="text-secondary">Coordinación administra la organización académica. Docentes y estudiantes consultan únicamente los grupos que les corresponden.</p><p class="mb-0 text-secondary">Los cambios se registran en la bitácora. No se eliminan cuentas con historial.</p></div></aside></div>
    </div>
@endsection
