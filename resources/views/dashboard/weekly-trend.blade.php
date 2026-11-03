@php
    $trendWeeks = $absenceRanking['weekly_trend'] ?? [];
@endphp
<section class="card mb-4" id="teacher-weekly-trend" aria-labelledby="teacher-weekly-trend-heading">
    <div class="card-header">
        <h2 class="card-title" id="teacher-weekly-trend-heading">Tendencia semanal de asistencia</h2>
        <span class="badge-soft">Grupo y cómputo seleccionados</span>
    </div>
    <div class="card-body">
        <p class="text-secondary">Conteos de resultados individuales en clases finalizadas, agrupados por la semana académica (lunes a domingo, hora de El Salvador). Una clase puede aportar varios resultados: uno por estudiante elegible. Las clases futuras, canceladas y sin inicio no se cuentan como realizadas. Los datos no constituyen una evaluación oficial.</p>
        @if($trendWeeks === [])
            <p class="text-secondary mb-0">Todavía no hay clases finalizadas con estudiantes elegibles para mostrar una tendencia en este cómputo.</p>
        @else
            @php
            $chartWeeks = array_slice($trendWeeks, -10);
            $chartMaximum = max(array_merge([1], array_map(static fn (array $week) => max($week['present'], $week['absent'], $week['justified']), $chartWeeks)));
            $series = [
                'present' => ['name' => 'Presentes', 'css' => 'present'],
                'absent' => ['name' => 'Ausentes', 'css' => 'absent'],
                'justified' => ['name' => 'Justificadas', 'css' => 'justified'],
            ];
            $pointsBySeries = [];
            foreach ($series as $key => $_) {
                $pointsBySeries[$key] = [];
                foreach ($chartWeeks as $index => $week) {
                    $x = count($chartWeeks) === 1 ? 460 : 65 + 790 * $index / (count($chartWeeks) - 1);
                    $y = 165 - 125 * $week[$key] / $chartMaximum;
                    $pointsBySeries[$key][] = ['x' => round($x, 2), 'y' => round($y, 2)];
                }
            }
            @endphp
            <div class="weekly-trend-legend" aria-label="Leyenda del gráfico">
                @foreach($series as $seriesItem)
                    <span class="weekly-trend-legend-item weekly-trend-legend-item--{{ $seriesItem['css'] }}">{{ $seriesItem['name'] }}</span>
                @endforeach
            </div>
            @if(count($trendWeeks) > count($chartWeeks))
                <p class="small text-secondary">La gráfica muestra las últimas 10 semanas con clases realizadas. La tabla incluye todas las semanas del cómputo.</p>
            @endif
            <div class="weekly-trend-visual" role="region" aria-label="Gráfico de tendencia semanal; la tabla inferior contiene los mismos datos" tabindex="0">
                <svg viewBox="0 0 920 215" role="img" aria-labelledby="weekly-trend-svg-title weekly-trend-svg-description">
                    <title id="weekly-trend-svg-title">Presentes, ausentes y justificadas por semana</title>
                    <desc id="weekly-trend-svg-description">Líneas de tendencia para los resultados semanales del grupo y cómputo seleccionados. Los datos completos se ofrecen en la tabla siguiente.</desc>
                    <line class="weekly-trend-axis" x1="55" y1="165" x2="865" y2="165" />
                    <line class="weekly-trend-axis" x1="55" y1="40" x2="55" y2="165" />
                    <text x="50" y="169" class="weekly-trend-tick" text-anchor="end">0</text>
                    <text x="50" y="44" class="weekly-trend-tick" text-anchor="end">{{ $chartMaximum }}</text>
                    @foreach($chartWeeks as $index => $week)
                        <text x="{{ $pointsBySeries['present'][$index]['x'] }}" y="192" text-anchor="middle" class="weekly-trend-tick">{{ \Carbon\CarbonImmutable::parse($week['week_start'])->format('d/m') }}</text>
                    @endforeach
                    @foreach($series as $key => $seriesItem)
                        <polyline class="weekly-trend-line weekly-trend-line--{{ $seriesItem['css'] }}" points="{{ collect($pointsBySeries[$key])->map(fn (array $point) => $point['x'].','.$point['y'])->implode(' ') }}" />
                        @foreach($pointsBySeries[$key] as $point)
                            <circle class="weekly-trend-point weekly-trend-point--{{ $seriesItem['css'] }}" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" />
                        @endforeach
                    @endforeach
                </svg>
            </div>
            <div class="table-responsive" role="region" aria-label="Tabla de la tendencia semanal de asistencia" tabindex="0">
                <table class="table table-vcenter weekly-trend-table">
                    <caption>Resultados semanales por grupo y cómputo. Cobertura: registros válidos sobre oportunidades de asistencia en clases finalizadas.</caption>
                    <thead><tr><th scope="col">Semana desde</th><th scope="col">Clases realizadas</th><th scope="col">Presentes</th><th scope="col">Ausentes</th><th scope="col">Justificadas</th><th scope="col">Cobertura de resultados</th><th scope="col">Pendientes</th></tr></thead>
                    <tbody>
                        @foreach($trendWeeks as $week)
                            <tr>
                                <th scope="row">{{ $week['week_start'] }}</th>
                                <td>{{ $week['completed_sessions'] }}</td>
                                <td>{{ $week['present'] }}</td>
                                <td>{{ $week['absent'] }}</td>
                                <td>{{ $week['justified'] }}</td>
                                <td>{{ $week['resolved'] }}/{{ $week['eligible'] }}{{ $week['coverage_percentage'] === null ? ' · —' : ' · '.$week['coverage_percentage'].' %' }}
                                    @if($week['missing'] + $week['inconsistent'] > 0)
                                        <small class="d-block text-secondary">{{ $week['missing'] }} sin resultado, {{ $week['inconsistent'] }} inconsistentes</small>
                                    @endif
                                </td>
                                <td>{{ $week['pending_requests'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
