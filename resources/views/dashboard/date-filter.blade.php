<div class="mb-3" id="dashboard-date-filter">
    <form method="GET" action="{{ route('dashboard') }}#dashboard-date-filter" class="d-flex align-items-end gap-2 flex-wrap">
        @if($absenceRanking['cycle'] !== null)
            <input type="hidden" name="ranking_cycle_id" value="{{ $absenceRanking['cycle']->id }}">
        @endif
        <input type="hidden" name="ranking_group_id" value="{{ $absenceRanking['group']->id }}">
        <input type="hidden" name="ranking_period_id" value="{{ $absenceRanking['period']->id }}">
        <input type="hidden" name="ranking_sort" value="{{ $absenceRanking['sort'] }}">
        <div>
            <label for="ranking-date-from" class="form-label">Desde (fecha académica)</label>
            <input type="date" id="ranking-date-from" name="ranking_date_from" class="form-control" min="{{ $absenceRanking['period']->starts_on->toDateString() }}" max="{{ $absenceRanking['period']->ends_on->toDateString() }}" value="{{ $absenceRanking['date_filter']['from'] ?? '' }}">
        </div>
        <div>
            <label for="ranking-date-to" class="form-label">Hasta (inclusive)</label>
            <input type="date" id="ranking-date-to" name="ranking_date_to" class="form-control" min="{{ $absenceRanking['period']->starts_on->toDateString() }}" max="{{ $absenceRanking['period']->ends_on->toDateString() }}" value="{{ $absenceRanking['date_filter']['to'] ?? '' }}">
        </div>
        <button type="submit" class="btn btn-outline-dark">Aplicar fechas</button>
        @if($absenceRanking['date_filter']['from'] !== null || $absenceRanking['date_filter']['to'] !== null)
            <a class="btn btn-outline-dark" href="{{ route('dashboard', ['ranking_cycle_id' => $absenceRanking['cycle']?->id, 'ranking_group_id' => $absenceRanking['group']->id, 'ranking_period_id' => $absenceRanking['period']->id, 'ranking_sort' => $absenceRanking['sort']]) }}#dashboard-date-filter">Restablecer fechas</a>
        @endif
    </form>
    <p class="small text-secondary mt-2 mb-0">Fechas del cómputo: {{ $absenceRanking['period']->starts_on->format('d/m/Y') }} al {{ $absenceRanking['period']->ends_on->format('d/m/Y') }}. Un intervalo sin sesiones mostrará resultados vacíos.</p>
    @error('ranking_date_from')<p class="text-danger small mt-2" role="alert">{{ $message }}</p>@enderror
    @error('ranking_date_to')<p class="text-danger small mt-2" role="alert">{{ $message }}</p>@enderror
    <p class="small text-secondary mt-2 mb-0">
        @if($absenceRanking['date_filter']['affects_ranking'])
            El rango ajusta el ranking, el detalle, la tendencia y la matriz mediante el calculador académico existente. Las fechas son inclusivas y corresponden a El Salvador; las cifras filtradas son exploratorias, no un cierre oficial.
        @else
            En un cómputo cerrado, el ranking y sus denominadores conservan el snapshot completo. El rango solo recorta las sesiones del detalle, la tendencia y la matriz; no vuelve a certificar ni modifica el cierre.
        @endif
        Los indicadores operativos del cómputo no se recortan por fechas. Al cambiar de ciclo o grupo, se restablece el rango.
    </p>
</div>
