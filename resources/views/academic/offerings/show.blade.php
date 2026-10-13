@extends('layouts.app')
@section('title', 'Detalle del grupo')
@section('content')
    <a href="{{ route('offerings.index') }}" class="back-link">
        ← Materias y grupos</a><x-page-heading :title="$offering->subject->name" :subtitle="$offering->cycle->code . ' · Grupo ' . $offering->group_code" />
    <div class="card mb-4">
        <div class="card-body">
            <div class="detail-grid">
                <div><span>Docente asignado</span><strong>{{ $offering->teacher->name }}</strong></div>
                <div><span>Cupo del grupo</span><strong>{{ $offering->capacity }} estudiantes</strong></div>
                <div><span>Cómputos</span><strong>{{ $offering->periods->count() }}</strong></div>
                <div><span>Sesiones
                        programadas</span><strong>{{ $offering->sessions->where('state', \App\Enums\SessionState::Scheduled)->count() }}</strong>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">Sesiones del grupo</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Modalidad</th>
                        <th>Cómputo</th>
                        <th>Estado</th>
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
                    </tr>@empty<tr>
                            <td colspan="4" class="text-secondary text-center">Sin sesiones programadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                {{ auth()->user()->role === \App\Enums\Role::Student ? 'Mi inscripción' : 'Estudiantes inscritos' }}</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Carné</th>
                        <th>Vigencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->student->name }}</td>
                            <td>{{ $enrollment->student->student_code ?? 'Sin carné' }}</td>
                            <td>{{ $enrollment->active_from->format('d/m/Y') }} —
                                {{ $enrollment->active_to?->format('d/m/Y') ?? 'Sin retiro' }}</td>
                    </tr>@empty<tr>
                            <td colspan="3" class="text-secondary text-center">Sin inscripciones visibles.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
