@extends('layouts.app')
@section('title', 'Historial académico')
@section('content')
<x-page-heading title="Historial académico" subtitle="Consulta histórica por ciclo, grupo y cómputo; horarios en America/El_Salvador." />
<form class="card card-body mb-4" method="GET" action="{{ route('history.index') }}">
    <label for="history-cycle" class="form-label">Ciclo</label>
    <div class="d-flex gap-2 flex-wrap">
        <select class="form-select" id="history-cycle" name="cycle_id" style="max-width:22rem">
            <option value="">Todos los ciclos</option>
            @foreach($cycles as $cycle)<option value="{{ $cycle->id }}" @selected($cycleId === $cycle->id)>{{ $cycle->code }}</option>@endforeach
        </select>
        <button class="btn btn-primary" type="submit">Filtrar</button>
    </div>
</form>
@if($enrollments !== null)
    <div class="card mb-4"><div class="card-header"><h2 class="card-title">Mis inscripciones</h2></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Ciclo / materia</th><th>Grupo</th><th>Vigencia</th><th>Consultar</th></tr></thead><tbody>
    @forelse($enrollments as $item)<tr>
        <td>{{ $item->offering->cycle->code }} · {{ $item->offering->subject->name }}</td><td>{{ $item->offering->group_code }}</td>
        <td>{{ $item->active_from->format('d/m/Y') }} — {{ $item->active_to?->format('d/m/Y') ?? 'Sin retiro' }}</td>
        <td><a href="{{ route('history.index', array_filter(['cycle_id' => $cycleId, 'enrollment_id' => $item->id])) }}">Ver cómputos</a></td>
    </tr>@empty<tr><td colspan="4">No tienes inscripciones para este ciclo.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $enrollments->links() }}</div></div>
    @if($selection)
        <div class="card mb-4"><div class="card-body"><h2 class="h3">Resultado personal por cómputo</h2>
        <p class="text-secondary">Los cierres usan el snapshot histórico verificado; los cómputos abiertos muestran resultados en revisión.</p>
        <nav class="d-flex flex-wrap gap-2" aria-label="Elegir cómputo">
            @foreach($periods as $period)<a class="btn btn-sm {{ $selectedPeriod?->id === $period->id ? 'btn-primary' : 'btn-outline-dark' }}" href="{{ route('history.index', ['enrollment_id' => $selection->id, 'period_id' => $period->id, 'cycle_id' => $cycleId]) }}">Cómputo {{ $period->number }}</a>@endforeach
        </nav>
        @if($calculation)
            @php($counts = $calculation['counts'] ?? [])
            <p class="mt-3"><strong>Fuente:</strong> {{ ($calculation['source'] ?? 'live') === 'snapshot' ? 'Cierre certificado' : 'Resultados provisionales' }}
                · <strong>Presentes:</strong> {{ $counts['present'] ?? 0 }} · <strong>Ausentes:</strong> {{ $counts['absent'] ?? 0 }} · <strong>Justificadas:</strong> {{ $counts['justified'] ?? 0 }}
                · <strong>Elegibles:</strong> {{ $counts['eligible'] ?? 0 }}
                · <strong>Asistencia:</strong> {{ $calculation['percentage'] === null ? 'No aplicable' : $calculation['percentage'].' %' }}
            </p>
            @if(($counts['eligible'] ?? 0) === 0)<p class="alert alert-info">No existen sesiones evaluables dentro de la vigencia de tu inscripción. No corresponde mostrar 0 % ni 100 %.</p>@endif
            <div class="table-responsive" tabindex="0" role="region" aria-label="Resultados de mis sesiones"><table class="table table-vcenter"><thead><tr><th>Sesión</th><th>Estado</th><th>Asistencia</th><th>Permiso</th><th>Detalle</th></tr></thead><tbody>
                @forelse($historyFacts as $fact)
                    <tr><td>{{ \Carbon\CarbonImmutable::parse($fact['starts_at'])->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}</td>
                    <td>{{ ['scheduled' => 'Futura/programada', 'in_progress' => 'En curso', 'completed' => 'Realizada', 'cancelled' => 'Cancelada', 'unstarted' => 'Sin inicio registrado'][$fact['state']] ?? $fact['state'] }}</td>
                    <td>{{ ['present' => 'Presente', 'absent' => 'Ausente', 'justified' => 'Justificada'][$fact['status']] ?? 'Sin resultado' }}</td>
                    <td>{{ ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'][$fact['request_status']] ?? 'Sin solicitud' }}</td>
                    <td><a href="{{ route('sessions.show', $fact['id']) }}">Ver sesión</a></td></tr>
                @empty<tr><td colspan="5">Sin sesiones dentro de tu vigencia.</td></tr>@endforelse
            </tbody></table></div>
            {{ $historyFacts->links() }}
        @else<p class="text-secondary mt-3">No hay resultados para este cómputo.</p>@endif
        </div></div>
    @endif
@else
    <div class="card"><div class="card-header"><h2 class="card-title">Grupos bajo mi responsabilidad</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Ciclo</th><th>Materia / Grupo</th><th>Resultados</th></tr></thead><tbody>
    @forelse($groups as $group)<tr><td>{{ $group->cycle->code }}</td><td>{{ $group->subject->name }} · {{ $group->group_code }}</td><td><a href="{{ route('offerings.show', $group) }}">Ver resultados y versiones de cierre</a></td></tr>
    @empty<tr><td colspan="3">No hay grupos accesibles.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $groups->links() }}</div></div>
@endif
@endsection
