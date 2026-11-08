@extends('layouts.app')
@section('title', 'Calendario académico')
@section('content')
<x-page-heading title="Calendario de sesiones" subtitle="Fechas y horas en {{ $timezone }}. Solo se muestran sesiones de grupos autorizados." />
@if(auth()->user()->role === \App\Enums\Role::Teacher)
<p class="d-flex gap-2 flex-wrap"><a href="{{ route('requests.index') }}" class="btn btn-sm btn-outline-dark">Ver permisos pendientes</a><a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-dark">Ver seguimiento de estudiantes</a></p>
@endif
<form method="GET" action="{{ route('calendar.index') }}" class="card card-body mb-4"><div class="row g-3">
    <div class="col-6 col-md-3"><label class="form-label" for="calendar-from">Desde</label><input type="date" class="form-control" name="from" id="calendar-from" value="{{ $from }}" required></div>
    <div class="col-6 col-md-3"><label class="form-label" for="calendar-to">Hasta</label><input type="date" class="form-control" name="to" id="calendar-to" value="{{ $to }}" required></div>
    <div class="col-6 col-md-3"><label class="form-label" for="calendar-cycle">Ciclo</label><select class="form-select" name="cycle_id" id="calendar-cycle"><option value="">Todos</option>@foreach($cycles as $cycle)<option value="{{ $cycle->id }}" @selected((int)($filters['cycle_id'] ?? 0) === $cycle->id)>{{ $cycle->code }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3"><label class="form-label" for="calendar-group">Grupo</label><select class="form-select" name="offering_id" id="calendar-group"><option value="">Todos</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((int)($filters['offering_id'] ?? 0) === $group->id)>{{ $group->subject->name }} · {{ $group->group_code }}</option>@endforeach</select></div>
    @if(auth()->user()->role === \App\Enums\Role::Coordinator)<div class="col-md-4"><label class="form-label" for="calendar-teacher">Docente</label><select class="form-select" name="teacher_id" id="calendar-teacher"><option value="">Todos</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected((int)($filters['teacher_id'] ?? 0) === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>@endif
    <div class="col-12"><button class="btn btn-primary" type="submit">Filtrar calendario</button></div>
</div></form>
<div class="card"><div class="card-header"><h2 class="card-title">Sesiones en el período</h2></div><div class="table-responsive" role="region" tabindex="0" aria-label="Sesiones autorizadas"><table class="table table-vcenter"><thead><tr><th>Fecha y hora</th><th>Materia / grupo</th><th>Modalidad</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
@forelse($sessions as $session)<tr>
    <td>{{ $session->starts_at->timezone($timezone)->format('d/m/Y H:i') }} — {{ $session->ends_at->timezone($timezone)->format('d/m/Y H:i') }}</td>
    <td>{{ $session->offering->subject->name }} · {{ $session->offering->group_code }}</td>
    <td>{{ ['in_person' => 'Presencial', 'online' => 'En línea', 'event' => 'Evento'][$session->modality] ?? $session->modality }}</td>
    <td>{{ $session->state->label() }}</td><td><a href="{{ route('sessions.show', $session) }}">Abrir</a></td>
</tr>@empty<tr><td colspan="5">Sin sesiones en este intervalo.</td></tr>@endforelse
</tbody></table></div><div class="card-footer">{{ $sessions->links() }}</div></div>
@endsection
