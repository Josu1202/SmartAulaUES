@if($session->started_at)
    <p class="text-secondary">Inicio real: {{ $session->started_at->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i:s') }}.</p>
@elseif($session->state === \App\Enums\SessionState::InProgress)
    <div class="alert alert-warning">No hay inicio real almacenado. Esta sesión no puede finalizarse automáticamente. Puede finalizarse manualmente; Coordinación puede recuperar el inicio si existe una única transición fiable en la auditoría.</div>
@endif
@if($session->state === \App\Enums\SessionState::InProgress && $session->started_at && $session->ends_at->timezone(config('smartaulaues.display_timezone'))->toDateString() > $session->started_at->timezone(config('smartaulaues.display_timezone'))->toDateString())
    <p class="text-secondary">El horario atraviesa medianoche. El respaldo diario respetará el fin programado; la finalización manual continúa disponible.</p>
@endif
@if(auth()->user()->role === \App\Enums\Role::Coordinator && $writable)
    @if($session->state === \App\Enums\SessionState::InProgress && !$session->started_at)
        <details class="mb-3"><summary>Recuperar inicio desde la auditoría</summary><form method="POST" action="{{ route('admin.sessions.review', [$session, 'recover-start']) }}" class="mt-2">@csrf<x-form-field name="reason" id="recover-start-reason" label="Motivo de recuperación" required minlength="5" maxlength="1000" /><button class="btn btn-outline-dark">Verificar y recuperar inicio</button></form></details>
    @endif
    @if(in_array($session->state, [\App\Enums\SessionState::Scheduled, \App\Enums\SessionState::Completed], true) && $session->ends_at <= now())
        <details class="mb-3"><summary>Revisar finalización y resultados</summary>
            <p class="mt-2">Acción administrativa excepcional: confirma la evidencia de una sesión programada contradictoria o repara una finalización incompleta. Conserva presentes; genera solo ausencias o justificadas faltantes y concilia resultados con permisos aprobados. No registra presencias ni reabre portales.</p>
            <form method="POST" action="{{ route('admin.sessions.review', [$session, 'reconcile']) }}">@csrf<x-form-field name="reason" id="reconcile-reason" label="Evidencia revisada y motivo" required minlength="5" maxlength="1000" /><button class="btn btn-outline-dark">Conciliar finalización</button></form>
        </details>
    @endif
@endif
