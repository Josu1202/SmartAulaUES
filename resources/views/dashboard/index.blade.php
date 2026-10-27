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
                class="stat-label">{{ $user->role === \App\Enums\Role::Student ? 'Mis materias' : 'Grupos disponibles' }}</span><strong>{{ $offerings->total() }}</strong><small>En
                tu espacio académico</small></div>
        <div class="card stat-card"><span class="stat-icon"><x-icon name="users" /></span><span
                class="stat-label">{{ $user->role === \App\Enums\Role::Student ? 'Mis inscripciones' : ($user->role === \App\Enums\Role::Coordinator ? 'Estudiantes activos' : 'Inscripciones') }}</span><strong>{{ $user->role === \App\Enums\Role::Student ? $offerings->total() : $studentCount }}</strong><small>{{ $user->role === \App\Enums\Role::Teacher ? 'Puede haber estudiantes en varios grupos' : 'Datos del sistema' }}</small>
        </div>
        <div class="card stat-card"><span class="stat-icon"><x-icon name="calendar" /></span><span
                class="stat-label">Próximas sesiones</span><strong>{{ $sessions->count() }}</strong><small>Hasta 5 próximas
                en este panel</small></div>
        <div class="card stat-card stat-policy"><span class="stat-icon"><x-icon name="file" /></span><span
                class="stat-label">Permisos pendientes</span><strong data-pending-count>{{ $statistics['pending_requests'] }}</strong><small>{{ $user->role === \App\Enums\Role::Student ? 'Mis solicitudes por resolver' : 'Solicitudes por revisar' }}</small></div>
    </div>
    @include('dashboard.operational-charts', ['showYears' => $user->role === \App\Enums\Role::Coordinator])
    @if($user->role === \App\Enums\Role::Teacher)
        <section class="card mb-4" id="absence-ranking" aria-labelledby="absence-ranking-heading">
            <div class="card-header">
                <h2 class="card-title" id="absence-ranking-heading">Estudiantes con más ausencias</h2>
                <span class="badge-soft">Seguimiento docente</span>
            </div>
            <div class="card-body">
                <p class="text-secondary">Ranking de faltas sin justificar en un grupo y cómputo. La tasa de inasistencia usa faltas sin justificar / sesiones elegibles; las justificadas se muestran aparte. No es una sanción ni una evaluación oficial.</p>
                @if($absenceRanking['groups']->isNotEmpty())
                    <form method="GET" action="{{ route('dashboard') }}#absence-ranking" class="d-flex align-items-end gap-2 flex-wrap mb-3">
                        <div>
                            <label for="ranking-group" class="form-label">Grupo</label>
                            <select name="ranking_group_id" id="ranking-group" class="form-select">
                                @foreach($absenceRanking['groups'] as $rankingGroup)
                                    <option value="{{ $rankingGroup->id }}" @selected($absenceRanking['group']?->id === $rankingGroup->id)>{{ $rankingGroup->subject->name }} · Grupo {{ $rankingGroup->group_code }} · {{ $rankingGroup->cycle->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-outline-dark" type="submit">Consultar grupo</button>
                    </form>
                    <p class="small text-secondary mb-3">Para cambiar de grupo, selecciónelo y pulse «Consultar grupo». Se abrirá su primer cómputo.</p>
                @endif
                @if($absenceRanking['periods']->isNotEmpty())
                    <nav aria-label="Cómputos del ranking" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="text-secondary">Cómputo:</span>
                        @foreach($absenceRanking['periods'] as $rankingPeriod)
                            <a class="btn btn-sm {{ $absenceRanking['period']?->id === $rankingPeriod->id ? 'btn-primary' : 'btn-outline-dark' }}" href="{{ route('dashboard', ['ranking_group_id' => $absenceRanking['group']->id, 'ranking_period_id' => $rankingPeriod->id]) }}#absence-ranking" @if($absenceRanking['period']?->id === $rankingPeriod->id) aria-current="page" @endif>{{ $rankingPeriod->number }}</a>
                        @endforeach
                    </nav>
                @endif
                @if($absenceRanking['error'])
                    <div class="alert alert-warning" role="status">{{ $absenceRanking['error'] }} Consulte los resultados del grupo o contacte a Coordinación.</div>
                @elseif($absenceRanking['period'] === null)
                    <p class="mb-0 text-secondary">{{ $absenceRanking['group'] === null ? 'Aún no tiene grupos asignados.' : 'Este grupo todavía no tiene cómputos definidos.' }}</p>
                @else
                    <p class="small text-secondary">Cómputo {{ $absenceRanking['period']->number }} · {{ match($absenceRanking['source']) { 'snapshot' => 'Resultados del cierre conservado', 'review' => 'Datos en revisión', default => 'Datos provisionales' } }} · Se muestran hasta 10 de {{ $absenceRanking['total_students'] }} inscripciones. «—» indica que aún no hay porcentaje calculable.</p>
                    <div class="table-responsive" role="region" aria-label="Ranking de ausencias del grupo; puede desplazarse horizontalmente" tabindex="0">
                        <table class="table table-vcenter absence-ranking-table">
                            <thead><tr><th scope="col">Posición</th><th scope="col">Estudiante</th><th scope="col">Carné</th><th scope="col">Faltas</th><th scope="col">Sesiones elegibles</th><th scope="col">Inasistencia</th><th scope="col">Justificadas</th><th scope="col">Detalle</th></tr></thead>
                            <tbody>
                                @forelse($absenceRanking['rows'] as $rankingPosition => $rankedStudent)
                                    <tr>
                                        <td>{{ $rankingPosition + 1 }}</td>
                                        <th scope="row">{{ $rankedStudent['name'] }}
                                            @if($rankedStudent['state'] !== 'provisional')<small class="d-block text-secondary">{{ match($rankedStudent['state']) { 'no_sessions' => 'Sin sesiones evaluables', 'incomplete' => 'Resultados incompletos', default => 'Política no disponible' } }}</small>@endif
                                            @if($rankedStudent['pending_requests'] > 0)<small class="d-block text-secondary">Permisos pendientes: {{ $rankedStudent['pending_requests'] }}</small>@endif
                                        </th>
                                        <td>{{ $rankedStudent['code'] ?? 'Sin carné' }}</td>
                                        <td>{{ $rankedStudent['absent'] }}</td>
                                        <td>{{ $rankedStudent['eligible'] }}</td>
                                        <td class="text-nowrap">{{ $rankedStudent['absence_percentage'] === null ? '—' : $rankedStudent['absence_percentage'].' %' }}</td>
                                        <td>{{ $rankedStudent['justified'] }}</td>
                                        <td><a class="btn btn-sm btn-outline-dark text-nowrap" aria-label="Ver detalle de {{ $rankedStudent['name'] }}" href="{{ route('dashboard', ['ranking_group_id' => $absenceRanking['group']->id, 'ranking_period_id' => $absenceRanking['period']->id, 'ranking_enrollment_id' => $rankedStudent['enrollment_id']]) }}#ranking-student-detail">Ver detalle</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-secondary">No hay estudiantes inscritos para este cómputo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
        @if($absenceRanking['detail'] !== null)
            <section class="card mb-4" id="ranking-student-detail" aria-labelledby="ranking-student-detail-heading">
                <div class="card-header"><h2 class="card-title" id="ranking-student-detail-heading">Detalle de {{ $absenceRanking['detail']['student']['name'] }} · Cómputo {{ $absenceRanking['period']->number }}</h2><a href="#absence-ranking" class="section-link">Volver al ranking</a></div>
                <div class="card-body">
                    <p class="text-secondary">{{ $absenceRanking['detail']['student']['code'] ?? 'Sin carné' }} · {{ $absenceRanking['detail']['student']['absent'] }} faltas sin justificar, {{ $absenceRanking['detail']['student']['justified'] }} justificadas y {{ $absenceRanking['detail']['student']['eligible'] }} clases evaluables. Los estados futuros o cancelados no cuentan como ausencia.</p>
                    <div class="table-responsive" role="region" aria-label="Sesiones del estudiante seleccionado" tabindex="0">
                        <table class="table table-vcenter">
                            <thead><tr><th scope="col">Fecha académica</th><th scope="col">Estado de clase</th><th scope="col">Resultado</th><th scope="col">Permiso</th></tr></thead>
                            <tbody>
                                @forelse($absenceRanking['detail']['sessions'] as $fact)
                                    <tr>
                                        <td>{{ $fact['academic_date'] }}</td>
                                        <td>{{ match($fact['state']) { 'completed' => 'Finalizada', 'cancelled' => 'Cancelada', 'unstarted' => 'Sin inicio', 'in_progress' => 'En curso', default => 'Programada' } }}</td>
                                        <td>{{ match($fact['status']) { 'present' => 'Presente', 'absent' => 'Ausente', 'justified' => 'Justificada', default => 'Sin resultado' } }}</td>
                                        <td>{{ match($fact['request_status']) { 'pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado', default => '—' } }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-secondary">Sin sesiones dentro de la vigencia de la inscripción.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    @endif
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
                                · Grupo {{ $offering->group_code }} · {{ $offering->cycle->code }}{{ $offering->cycle->reopened_at ? ' · En revisión' : '' }}</small></span><span
                            class="offering-row-count">{{ $offering->enrollments_count }}<small>inscritos</small></span><x-icon
                        name="arrow" /></a>@empty<div class="empty-small">Aún no tienes grupos asignados.</div>
                @endforelse
            </div>
            {{ $offerings->links() }}
            <div class="card-bottom-note"><x-icon
                    name="info" />Se muestran los grupos autorizados para tu cuenta.
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
