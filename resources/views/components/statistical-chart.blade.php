@props(['title', 'data', 'subtitle' => ''])
@php($maximum = max([1, ...array_values($data)]))
<section class="card h-100">
    <div class="card-header"><h2 class="card-title">{{ $title }}</h2></div>
    <div class="card-body">
        @if($subtitle)
            <p class="text-secondary">{{ $subtitle }}</p>
        @endif

        @if(array_sum($data) === 0)
            <div class="empty-small">Aún no hay datos para esta gráfica.</div>
        @else
            <div class="stat-chart" role="img" aria-label="{{ $title }}: {{ collect($data)->map(fn($count, $label) => $label.': '.$count)->implode(', ') }}">
                @foreach($data as $label => $count)
                    <div class="chart-row">
                        <span>{{ $label }}</span>
                        <div class="chart-track" aria-hidden="true"><div class="chart-fill" style="width: {{ round(($count / $maximum) * 100, 2) }}%"></div></div>
                        <strong>{{ $count }}</strong>
                    </div>
                @endforeach
            </div>
            <details class="chart-data-details mt-3">
                <summary>Ver datos en tabla: {{ $title }}</summary>
                <div class="table-responsive" role="region" aria-label="Tabla de {{ $title }}" tabindex="0">
                    <table class="table table-vcenter">
                        <caption class="visually-hidden">{{ $title }}</caption>
                        <thead><tr><th scope="col">Categoría</th><th scope="col">Cantidad</th></tr></thead>
                        <tbody>
                            @foreach($data as $label => $count)
                                <tr><th scope="row">{{ $label }}</th><td>{{ $count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>
</section>
