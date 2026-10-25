@extends('layouts.app')
@section('title', 'Mi cuenta')
@section('content')
    <x-page-heading title="Mi cuenta" subtitle="Información asignada a tu perfil académico." />
    <div class="card profile-card">
        <div class="card-body"><span class="profile-avatar">{{ mb_substr($user->name, 0, 1) }}</span>
            <h2>{{ $user->name }}</h2><span class="badge-soft">{{ $user->role->label() }}</span>
            <dl class="profile-data">
                <dt>Correo electrónico</dt>
                <dd>{{ $user->email }}</dd>
                <dt>Carné</dt>
                <dd>{{ $user->student_code ?? 'No aplica' }}</dd>
                <dt>Estado</dt>
                <dd>{{ $user->active ? 'Activo' : 'Inactivo' }}</dd>
            </dl>
            <p class="text-secondary mb-0">Para cambios en tu cuenta, consulta a coordinación.</p>
        </div>
    </div>
    <div class="card profile-card mt-4"><div class="card-header"><h2 class="card-title">Cambiar mi contraseña</h2></div><div class="card-body">
        <p class="text-secondary">Después del cambio volverás a iniciar sesión. Las sesiones anteriores serán revocadas.</p>
        <form method="POST" action="{{ route('profile.password') }}"><input type="hidden" name="_form" value="{{ hash('sha256', 'profile/show.blade.php:1'.'|'.(route('profile.password'))) }}">@csrf @method('PUT')
            <x-form-field :scope="hash('sha256', 'profile/show.blade.php:1'.'|'.(route('profile.password')))" name="current_password" label="Contraseña actual" type="password" required autocomplete="current-password" />
            <x-form-field :scope="hash('sha256', 'profile/show.blade.php:1'.'|'.(route('profile.password')))" name="password" label="Nueva contraseña" type="password" required minlength="12" maxlength="128" autocomplete="new-password" help="Al menos 12 caracteres, diferente de la contraseña actual." />
            <x-form-field :scope="hash('sha256', 'profile/show.blade.php:1'.'|'.(route('profile.password')))" name="password_confirmation" label="Confirmar nueva contraseña" type="password" required autocomplete="new-password" />
            <button class="btn btn-primary" type="submit">Cambiar contraseña</button>
        </form>
    </div></div>
@endsection
