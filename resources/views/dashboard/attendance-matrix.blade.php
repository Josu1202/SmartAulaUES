<section class="card mb-4" id="attendance-matrix" aria-labelledby="attendance-matrix-title">
    <div class="card-header">
        <h2 class="card-title" id="attendance-matrix-title">Matriz de asistencia por estudiante y sesión</h2>
        <span class="badge-soft">Cómputo {{ $absenceRanking['period']->number }}</span>
    </div>
    <div class="card-body">
        <p class="text-secondary">Cada columna representa una sesión del cómputo seleccionado. Se distinguen los resultados, las clases no realizadas y las fechas fuera de la vigencia de cada inscripción. Los datos de cierres certificados se consultan desde la versión histórica. No permite editar asistencias.</p>
        @php($matrix = $absenceRanking['matrix'])
        @if($matrix['rows'] === [])
            <p role="status" class="mb-0 text-secondary">No hay estudiantes inscritos en esta página de la matriz.</p>
        @elseif($matrix['sessions'] === [])
            <p role="status" class="mb-0 text-secondary">Este cómputo todavía no tiene sesiones registradas.</p>
        @else
            <p class="small text-secondary">Se muestran {{ count($matrix['rows']) }} de {{ $matrix['total'] }} inscripciones en la página {{ $matrix['page'] }} de {{ $matrix['pages'] }}. Para consultar todas las sesiones, desplace horizontalmente la tabla.</p>
            <div class="table-responsive" role="region" tabindex="0" aria-label="Matriz de asistencia del grupo con desplazamiento horizontal">
                <table class="table table-vcenter attendance-matrix-table">
                    <caption class="visually-hidden">Resultados del grupo {{ $absenceRanking['group']->group_code }} en el cómputo {{ $absenceRanking['period']->number }}; cada sesión usa su fecha académica de El Salvador.</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="matrix-student-col">Estudiante</th>
                            @foreach($matrix['sessions'] as $matrixSession)
                                <th scope="col" class="matrix-session-col">
                                    <span class="d-block">{{ $matrixSession['date'] }}</span>
                                    <small class="d-block text-secondary">{{ match($matrixSession['state']) { 'completed' => 'Finalizada', 'cancelled' => 'Cancelada', 'unstarted' => 'Sin inicio', 'in_progress' => 'En curso', default => 'Programada' } }}</small>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matrix['rows'] as $matrixStudent)
                            <tr>
                                <th scope="row" class="matrix-student-col">
                                    {{ $matrixStudent['name'] }}
                                    <small class="d-block text-secondary">{{ $matrixStudent['code'] ?? 'Sin carné' }}</small>
                                </th>
                                @foreach($matrix['sessions'] as $matrixSession)
                                    @php($fact = $matrixStudent['facts'][$matrixSession['id']] ?? null)
                                    @php($status = $fact === null || !$fact['in_range'] ? 'Fuera de vigencia' : match (true) {
                                        $fact['state'] === 'cancelled' => 'Cancelada',
                                        $fact['state'] === 'unstarted' => 'Sin inicio',
                                        $fact['state'] === 'in_progress' => 'En curso',
                                        $fact['state'] !== 'completed' => 'Programada',
                                        $fact['status'] === null => 'Sin resultado',
                                        $fact['has_incident'] => 'Inconsistente',
                                        default => match ($fact['status']) { 'present' => 'Presente', 'absent' => 'Ausente', 'justified' => 'Justificada', default => 'Inconsistente' }
                                    })
                                    <td class="matrix-session-col">
                                        <span class="matrix-result">{{ $status }}</span>
                                        @if($fact !== null && $fact['in_range'] && $fact['request_status'] === 'pending')
                                            <small class="d-block text-secondary">Permiso pendiente</small>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @if($matrix['pages'] > 1)
        <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="text-secondary">Página {{ $matrix['page'] }} de {{ $matrix['pages'] }}</span>
            <nav aria-label="Páginas de la matriz de asistencia" class="d-flex gap-2">
                @if($matrix['page'] > 1)
                    <a class="btn btn-sm btn-outline-dark" href="{{ route('dashboard', ['ranking_group_id' => $absenceRanking['group']->id, 'ranking_period_id' => $absenceRanking['period']->id, 'matrix_page' => $matrix['page'] - 1]) }}#attendance-matrix">Anterior</a>
                @endif
                @if($matrix['page'] < $matrix['pages'])
                    <a class="btn btn-sm btn-outline-dark" href="{{ route('dashboard', ['ranking_group_id' => $absenceRanking['group']->id, 'ranking_period_id' => $absenceRanking['period']->id, 'matrix_page' => $matrix['page'] + 1]) }}#attendance-matrix">Siguiente</a>
                @endif
            </nav>
        </div>
    @endif
</section>
