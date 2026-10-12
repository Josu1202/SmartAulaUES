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
@endsection
