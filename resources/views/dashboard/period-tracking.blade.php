<section class="card mb-4" id="period-tracking" aria-labelledby="period-tracking-heading">
    <div class="card-header">
        <h2 class="card-title" id="period-tracking-heading">Seguimiento del cómputo</h2>
        <span class="badge-soft">Grupo autorizado</span>
    </div>
    <div class="card-body">
        @php($tracking = $absenceRanking['period_tracking'])
        <p class="text-secondary">
            Cómputo {{ $absenceRanking['period']->number }} ·
            {{ match($tracking['state']) {
                'closed' => 'Cómputo cerrado',
                'review' => 'En revisión (reabierto)',
                default => 'Cómputo abierto · resultados provisionales'
            } }}
        </p>
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
                <div class="card p-3 h-100">
                    <span class="text-secondary">Permisos pendientes del cómputo</span>
                    <strong class="fs-2">{{ $tracking['pending_requests'] }}</strong>
                    <small class="text-secondary">Solicitudes actuales en las sesiones de este cómputo.</small>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="card p-3 h-100">
                    <span class="text-secondary">Sesiones en curso</span>
                    <strong class="fs-2">{{ $tracking['in_progress_sessions'] }}</strong>
                    <small class="text-secondary">Sesiones que todavía figuran como iniciadas, sin finalizar.</small>
                </div>
            </div>
        </div>
        @if($tracking['state'] === 'closed' && $tracking['closed_at'] !== null)
            <p class="small text-secondary mb-2">Cierre registrado: {{ $tracking['closed_at']->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}.</p>
        @elseif($tracking['state'] === 'review')
            <p class="small text-secondary mb-2">El cómputo fue reabierto. Los resultados siguen en revisión hasta un nuevo cierre por Coordinación.</p>
        @endif
        @if($tracking['last_checked_at'] !== null)
            <p class="small text-secondary mb-0">Última comprobación registrada del cierre: {{ $tracking['last_checked_at']->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}. Es información histórica, no una validación en tiempo real.</p>
        @endif
    </div>
</section>
