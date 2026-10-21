@extends('layouts.app')
@section('title', 'Detalle del grupo')
@section('content')
    <a href="{{ route('offerings.index') }}" class="back-link">
        ← Materias y grupos</a><x-page-heading :title="$offering->subject->name" :subtitle="$offering->cycle->code . ' · Grupo ' . $offering->group_code">@can('update', $offering)<a class="btn btn-outline-dark" href="{{ route('admin.offerings.edit', $offering) }}">{{ $offering->cycle->status === 'closed' ? 'Consultar grupo' : 'Editar grupo' }}</a>@endcan</x-page-heading>
    @if($offering->cycle->status === 'closed')<div class="alert alert-info">Este ciclo está cerrado. Los datos académicos son de solo lectura.</div>@endif
    <div class="card mb-4">
        <div class="card-body">
            <div class="detail-grid">
                <div><span>Docente asignado</span><strong>{{ $offering->teacher->name }}</strong></div>
                <div><span>Plazas reservadas / cupo</span><strong>{{ $reservedSeats }} / {{ $offering->capacity }} estudiantes</strong></div>
                <div><span>Cómputos</span><strong>{{ $offering->periods->count() }}</strong></div>
                <div><span>Sesiones
                        programadas</span><strong>{{ $offering->sessions->where('state', \App\Enums\SessionState::Scheduled)->count() }}</strong>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4"><div class="card-header d-flex justify-content-between flex-wrap"><h2 class="card-title">Cómputos del grupo</h2>@can('create', \App\Models\AssessmentPeriod::class)@if($offering->cycle->status !== 'closed')<a class="btn btn-sm btn-primary" href="{{ route('admin.offerings.periods.create', $offering) }}">Crear cómputo</a>@endif @endcan</div>
        <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Cómputo</th><th>Inicio</th><th>Fin</th><th>Estado</th>@can('update', $offering)<th>Acciones</th>@endcan</tr></thead><tbody>
            @forelse($offering->periods->sortBy('number') as $period)<tr><td><strong>Cómputo {{ $period->number }}</strong></td><td>{{ $period->starts_on->format('d/m/Y') }}</td><td>{{ $period->ends_on->format('d/m/Y') }}</td><td><span class="badge-soft">{{ ['open' => 'Abierto', 'closing' => 'En cierre', 'closed' => 'Cerrado'][$period->state] }}</span></td>@can('update', $offering)<td><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.offerings.periods.edit', [$offering, $period]) }}">{{ $period->state === 'open' && $offering->cycle->status !== 'closed' ? 'Editar' : 'Consultar' }}</a></td>@endcan</tr>
            @empty<tr><td colspan="5" class="text-secondary text-center">Sin cómputos definidos.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between flex-wrap">
            <h2 class="card-title">Sesiones del grupo</h2>
            @can('manage', $offering)@if($offering->cycle->status !== 'closed')<a class="btn btn-sm btn-primary" href="{{ route('offerings.sessions.create', $offering) }}">Programar sesión</a>@endif @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Modalidad</th>
                        <th>Cómputo</th>
                        <th>Estado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offering->sessions->sortBy('starts_at') as $session)
                        <tr>
                            <td>{{ $session->starts_at->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}
                            </td>
                            <td>{{ ['in_person' => 'Presencial', 'online' => 'En línea', 'event' => 'Evento'][$session->modality] }}
                            </td>
                            <td>{{ $offering->periods->firstWhere('id', $session->period_id)->number }}</td>
                            <td><span class="badge-soft">{{ $session->state->label() }}</span></td>
                            <td><a class="btn btn-sm btn-outline-dark" href="{{ route('sessions.show', $session) }}">Abrir sesión</a></td>
                    </tr>@empty<tr>
                            <td colspan="4" class="text-secondary text-center">Sin sesiones programadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('academic.offerings.attendance-results')
    <div class="card">
        <div class="card-header d-flex justify-content-between flex-wrap">
            <h2 class="card-title">
                {{ auth()->user()->role === \App\Enums\Role::Student ? 'Mi inscripción' : 'Estudiantes inscritos' }}</h2>
            @can('create', \App\Models\Enrollment::class)@if($offering->cycle->status !== 'closed')<div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.offerings.enrollments.create', $offering) }}">Inscribir estudiante</a><a class="btn btn-sm btn-primary" href="{{ route('admin.offerings.students', $offering) }}">Cargar estudiantes por año</a></div>@endif @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Carné</th>
                        <th>Vigencia</th>
                        @can('update', $offering)<th>Acciones</th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse($enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->student->name }}</td>
                            <td>{{ $enrollment->student->student_code ?? 'Sin carné' }}</td>
                            <td>{{ $enrollment->active_from->format('d/m/Y') }} —
                                {{ $enrollment->active_to?->format('d/m/Y') ?? 'Sin retiro' }}</td>
                            @can('update', $offering)<td><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.offerings.enrollments.edit', [$offering, $enrollment]) }}">{{ $offering->cycle->status === 'closed' ? 'Consultar' : 'Vigencia / retiro' }}</a></td>@endcan
                    </tr>@empty<tr>
                            <td colspan="3" class="text-secondary text-center">Sin inscripciones visibles.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $enrollments->links() }}</div>
    </div>
@endsection
