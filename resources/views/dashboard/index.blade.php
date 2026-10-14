@extends('layouts.app')
@section('title', 'Inicio')
@section('content')
    <x-page-heading :title="'Hola, ' .
        ($user->role === \App\Enums\Role::Coordinator
            ? 'coordinación'
            : ($user->role === \App\Enums\Role::Teacher
                ? 'docente'
                : 'estudiante')) .
        '.'" subtitle="Tu espacio académico, en una sola vista."><span class="today-label"><x-icon
                name="calendar" />{{ now()->timezone(config('smartaulaues.display_timezone'))->locale('es')->translatedFormat('d M Y') }}</span></x-page-heading>
    <section class="dashboard-hero">
        <div><span class="eyebrow">CADA CLASE CUENTA</span>
            <h2>{{ $user->role === \App\Enums\Role::Student ? 'Tu recorrido académico.' : 'Tu campus, conectado.' }}</h2>
            <p>{{ $user->role === \App\Enums\Role::Student ? 'Consulta tus materias y las próximas sesiones de tus grupos.' : 'Organiza tus grupos y prepara el seguimiento de cada clase.' }}
            </p><a class="btn hero-button" href="{{ route('offerings.index') }}">Ver mis grupos<x-icon name="arrow" /></a>
        </div>
        <div class="hero-graphic" aria-hidden="true">
            <div class="hero-orbit"></div>
            <div class="hero-mini-card"><x-icon name="book" /><span>Vida académica<small>En un mismo
                        espacio</small></span><span class="hero-check"><x-icon name="check" /></span></div>
            <div class="hero-nfc"><x-icon name="nfc" /></div>
            <div class="hero-grid"><span></span><span></span><span></span><span></span></div>
        </div>
    </section>
    <div class="stat-grid">
        <div class="card stat-card"><span class="stat-icon"><x-icon name="book" /></span><span
                class="stat-label">{{ $user->role === \App\Enums\Role::Student ? 'Mis materias' : 'Grupos disponibles' }}</span><strong>{{ $offerings->count() }}</strong><small>En
                tu espacio académico</small></div>
        <div class="card stat-card"><span class="stat-icon"><x-icon name="users" /></span><span
                class="stat-label">{{ $user->role === \App\Enums\Role::Student ? 'Mis inscripciones' : ($user->role === \App\Enums\Role::Coordinator ? 'Estudiantes activos' : 'Inscripciones') }}</span><strong>{{ $user->role === \App\Enums\Role::Student ? $offerings->count() : $studentCount }}</strong><small>{{ $user->role === \App\Enums\Role::Teacher ? 'Puede haber estudiantes en varios grupos' : 'Datos del sistema' }}</small>
        </div>
        <div class="card stat-card"><span class="stat-icon"><x-icon name="calendar" /></span><span
                class="stat-label">Próximas sesiones</span><strong>{{ $sessions->count() }}</strong><small>Hasta 5 próximas
                en este panel</small></div>
        <div class="card stat-card stat-policy"><span class="stat-icon"><x-icon name="file" /></span><span
                class="stat-label">Permisos pendientes</span><strong data-pending-count>{{ $statistics['pending_requests'] }}</strong><small>{{ $user->role === \App\Enums\Role::Student ? 'Mis solicitudes por resolver' : 'Solicitudes por revisar' }}</small></div>
    </div>
    <div class="row g-4 mb-4"><div class="col-lg-6"><x-statistical-chart title="Sesiones por estado" :data="$statistics['sessions']" subtitle="Clases de los grupos autorizados para tu cuenta."/></div><div class="col-lg-6"><x-statistical-chart title="Resultados registrados" :data="$statistics['results']" subtitle="Resultados de las clases en curso y realizadas."/></div><div class="col-lg-6"><x-statistical-chart title="Presencias por método" :data="$statistics['methods']" subtitle="Cada resultado se cuenta una sola vez; los reintentos no aumentan esta gráfica."/></div>@if($user->role === \App\Enums\Role::Coordinator)<div class="col-lg-6"><x-statistical-chart title="Estudiantes por año de carrera" :data="$statistics['years']" subtitle="Cuentas activas; los registros antiguos sin año aparecen como Sin asignar."/></div>@endif</div>
    <div class="dashboard-grid">
        <section class="card">
            <div class="card-header">
                <h3 class="card-title">Tus materias y grupos</h3><a href="{{ route('offerings.index') }}"
                    class="section-link">Ver todos<x-icon name="arrow" /></a>
            </div>
            <div class="offering-list">
                @forelse($offerings as $offering)
                    <a class="offering-row" href="{{ route('offerings.show', $offering) }}"><span
                            class="subject-icon"><x-icon name="book" /></span><span
                            class="offering-row-name">{{ $offering->subject->name }}<small>{{ $offering->subject->code }}
                                · Grupo {{ $offering->group_code }}</small></span><span
                            class="offering-row-count">{{ $offering->enrollments_count }}<small>inscritos</small></span><x-icon
                        name="arrow" /></a>@empty<div class="empty-small">Aún no tienes grupos asignados.</div>
                @endforelse
            </div>
            <div class="card-bottom-note"><x-icon
                    name="info" />{{ config('smartaulaues.demo_enabled') ? 'Escenario académico con datos ficticios.' : 'Se muestran los grupos autorizados para tu cuenta.' }}
            </div>
        </section>
        <section class="card">
            <div class="card-header">
                <h3 class="card-title">Próximas clases</h3><span class="badge-soft">Programadas</span>
            </div>
            <div class="schedule-list">
                @forelse($sessions as $session)
                    <div class="schedule-row"><span
                            class="schedule-date">{{ $session->starts_at->timezone(config('smartaulaues.display_timezone'))->format('d') }}<small>{{ $session->starts_at->timezone(config('smartaulaues.display_timezone'))->locale('es')->translatedFormat('M') }}</small></span>
                        <div><strong>{{ $session->offering->subject->name }}</strong><small><x-icon
                                    name="clock" />{{ $session->starts_at->timezone(config('smartaulaues.display_timezone'))->format('H:i') }}
                                · Grupo {{ $session->offering->group_code }}</small></div>
                </div>@empty<div class="empty-small">No hay próximas sesiones programadas.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
