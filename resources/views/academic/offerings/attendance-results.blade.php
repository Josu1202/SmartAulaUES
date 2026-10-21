<section class="card mb-4" id="attendance-results" aria-labelledby="attendance-results-title">
    <div class="card-header"><h2 class="card-title" id="attendance-results-title">Resultados de asistencia</h2></div>
    <div class="card-body">
        <p>El mínimo requerido es 75 %. Las presencias y las ausencias justificadas con permiso aprobado suman al porcentaje. Solo se consideran las clases realizadas que correspondían al estudiante.</p>
        <p class="text-secondary">Resultados actualizables. La certificación del cómputo se incorpora en una etapa posterior.</p>
        <p class="small text-secondary">Desplaza la tabla horizontalmente para consultar todas las columnas.</p>
        @if($attendanceResults['periods']->isNotEmpty())
            <form method="GET" action="{{ route('offerings.show', $offering) }}#attendance-results" class="d-flex align-items-end gap-2 flex-wrap">
                <div><label class="form-label" for="attendance-period">Cómputo</label><select class="form-select" id="attendance-period" name="period_id">
                    @foreach($attendanceResults['periods'] as $resultPeriod)<option value="{{ $resultPeriod->id }}" @selected($attendanceResults['period']?->id === $resultPeriod->id)>Cómputo {{ $resultPeriod->number }} ({{ $resultPeriod->starts_on->format('d/m/Y') }} – {{ $resultPeriod->ends_on->format('d/m/Y') }})</option>@endforeach
                </select></div>
                <button class="btn btn-primary" type="submit">Consultar resultados</button>
            </form>
        @else
            <p class="mb-0">Este grupo no tiene cómputos definidos.</p>
        @endif
    </div>
    @if($attendanceResults['period'] !== null)
        <div class="table-responsive" role="region" aria-label="Resultados de asistencia por estudiante" tabindex="0">
            <table class="table table-vcenter" data-attendance-results>
                <caption class="visually-hidden">Resultados actualizables del cómputo {{ $attendanceResults['period']->number }}. Se incluyen todas sus clases elegibles, aunque los estudiantes se muestren por páginas.</caption>
                <thead><tr><th scope="col">Estudiante</th><th scope="col">Carné</th><th scope="col">Clases evaluables</th><th scope="col">Presentes</th><th scope="col">Justificadas</th><th scope="col">Ausentes</th><th scope="col">Porcentaje</th><th scope="col">Conclusión</th><th scope="col">Pendientes / incidencias</th></tr></thead>
                <tbody>
                    @forelse($attendanceResults['rows'] as $row)
                        @php($result = $row['calculation'])
                        <tr data-enrollment="{{ $row['enrollment_id'] }}">
                            <th scope="row">{{ $row['student_name'] }}@if(!$row['student_active'])<span class="d-block text-secondary">Cuenta inactiva · historial conservado</span>@endif</th>
                            <td>{{ $row['student_code'] ?? 'Sin carné' }}</td>
                            <td>{{ $result['counts']['eligible'] }}</td><td>{{ $result['counts']['present'] }}</td><td>{{ $result['counts']['justified'] }}</td><td>{{ $result['counts']['absent'] }}</td>
                            <td class="text-nowrap">{{ $result['percentage'] !== null ? $result['percentage'].' %' : '—' }}</td>
                            <td>{{ $result['conclusion'] ?? match($result['state']) { 'no_sessions' => 'Sin clases evaluables', 'incomplete' => 'Datos incompletos', default => 'Reglamento no disponible para cálculo' } }}</td>
                            <td>
                                @if($result['counts']['pending_requests'])<div>Permisos pendientes: {{ $result['counts']['pending_requests'] }}. El resultado puede cambiar.</div>@endif
                                @foreach(['future' => 'Clases futuras', 'in_progress' => 'Clases actualmente en curso', 'awaiting_start' => 'Clases pendientes de iniciar', 'overdue_unfinished' => 'Clases vencidas sin finalizar'] as $key => $label)
                                    @if($result['counts'][$key])<div>{{ $label }}: {{ $result['counts'][$key] }}.</div>@endif
                                @endforeach
                                @foreach(collect($result['incidents'])->countBy('code') as $code => $issueCount)
                                    <div>{{ match($code) { 'missing_result' => 'Falta el resultado de una clase realizada.', 'justified_without_approval' => 'Hay una justificación sin permiso aprobado.', 'approved_request_with_absent_result' => 'Hay un permiso aprobado que no coincide con el resultado.', 'completed_before_start' => 'Una clase figura realizada antes de su inicio.', default => 'Existe un resultado inconsistente.' } }} Casos: {{ $issueCount }}.</div>
                                @endforeach
                                @if(!$result['counts']['pending_requests'] && !$result['counts']['future'] && !$result['counts']['in_progress'] && !$result['counts']['awaiting_start'] && !$result['counts']['overdue_unfinished'] && $result['incidents'] === [])<span>Sin pendientes registrados.</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-secondary">Sin inscripciones visibles en esta página.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $attendanceResults['rows']->fragment('attendance-results')->links() }}</div>
    @endif
</section>
