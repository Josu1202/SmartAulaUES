@php($selectedPeriod = $attendanceResults['period'])
@if($selectedPeriod)
    <div class="mt-3">
        <p class="mb-2"><strong>{{ $selectedPeriod->state === 'closed' ? 'Cómputo cerrado' : ($selectedPeriod->reopened_at ? 'En revisión' : 'Cómputo abierto · resultados provisionales') }}</strong></p>
        @if($attendanceResults['source'] === 'snapshot' && $attendanceResults['report'])
            <p>Versión {{ $attendanceResults['report']->version }} · Generada el {{ $attendanceResults['report']->generated_at->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}. Resultados conservados en el cierre.</p>
        @elseif($selectedPeriod->reopened_at)
            <p>Resultados vivos en revisión. El nuevo cierre requiere una acción manual de Coordinación.</p>
        @endif
        @if($attendanceResults['snapshotError'])<div class="alert alert-danger" role="alert">{{ $attendanceResults['snapshotError'] }}</div>@endif
        @if($attendanceResults['versions']->isNotEmpty())
            <form method="GET" action="{{ route('offerings.show', $offering) }}#attendance-results" class="d-flex align-items-end gap-2 flex-wrap mb-3">
                @include('academic.offerings.catalog-context')
                <input type="hidden" name="period_id" value="{{ $selectedPeriod->id }}">
                <div><label class="form-label" for="report-version">Historial de cierres</label><select class="form-select" name="report_version" id="report-version">
                    <option value="">{{ $selectedPeriod->state === 'closed' ? 'Cierre vigente' : 'Resultados vivos' }}</option>
                    @foreach($attendanceResults['versions'] as $version)<option value="{{ $version->version }}" @selected((string) request('report_version') === (string) $version->version)>Versión {{ $version->version }} · {{ $version->generated_at?->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') ?? 'Fecha sin verificar' }}</option>@endforeach
                </select></div><button class="btn btn-outline-dark">Consultar versión</button>
            </form>
        @endif
        @can('manage', $offering)
            @if($selectedPeriod->closure_issues)
                <div class="alert alert-warning"><strong>Impedimentos del último intento de cierre</strong><ul class="mb-0 mt-2">@foreach($selectedPeriod->closure_issues as $issue)<li>{{ $issue }}</li>@endforeach</ul><p class="mb-0 mt-2">Resuelve las sesiones o permisos desde su detalle y vuelve a intentar el cierre.</p></div>
            @endif
        @endcan
        @if(auth()->user()->role === \App\Enums\Role::Coordinator && $offering->cycle->status !== 'closed')
            @if($selectedPeriod->state === 'closed' && $offering->active)
                <details><summary>Reabrir cómputo</summary><form class="mt-2" method="POST" action="{{ route('admin.periods.reopen', $selectedPeriod) }}"><input type="hidden" name="_form" value="{{ hash('sha256', 'academic/offerings/closure.blade.php:1'.'|'.(route('admin.periods.reopen', $selectedPeriod))) }}">@csrf
                    <x-form-field :scope="hash('sha256', 'academic/offerings/closure.blade.php:1'.'|'.(route('admin.periods.reopen', $selectedPeriod)))" name="reason" id="period-reopen-reason" label="Motivo de reapertura" required minlength="5" maxlength="1000" />
                    <button class="btn btn-outline-dark">Reabrir para revisión</button>
                </form></details>
            @elseif(!$offering->active)
                <p class="form-hint">Reactiva el grupo antes de cerrar o reabrir este cómputo manualmente. El procesador automático conserva sus reglas.</p>
            @elseif($selectedPeriod->state !== 'closed' && $selectedPeriod->ends_on->toDateString() < now('America/El_Salvador')->toDateString())
                <form method="POST" action="{{ route('admin.periods.close', $selectedPeriod) }}"><input type="hidden" name="_form" value="{{ hash('sha256', 'academic/offerings/closure.blade.php:2'.'|'.(route('admin.periods.close', $selectedPeriod))) }}">@csrf<button class="btn btn-primary">Validar y cerrar cómputo</button></form>
            @elseif($selectedPeriod->state === 'closed' && !$offering->active)
                <p class="form-hint">Reactiva el grupo antes de reabrir este cómputo para revisión.</p>
            @else
                <p class="form-hint">El cierre estará disponible al terminar la fecha final local del cómputo.</p>
            @endif
        @endif
    </div>
@endif
