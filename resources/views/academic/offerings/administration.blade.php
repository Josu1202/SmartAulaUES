@can('administer', $offering)
<section class="card mb-4" aria-labelledby="group-administration-title"><div class="card-body">
    <h2 class="card-title" id="group-administration-title">Administración del grupo</h2>
    @if($offering->active)
        @if($deactivationIssues)
            <div class="alert alert-warning" role="status"><strong>No se puede desactivar todavía.</strong><ul class="mb-0">@foreach($deactivationIssues as $issue)<li>{{ $issue }}</li>@endforeach</ul></div>
        @endif
        <p class="form-hint">Al confirmar, se revisará nuevamente si el grupo admite el cambio.</p>
        <details class="mb-3"><summary>Desactivar grupo</summary>
            <p class="mt-2">Se suspende la operación académica y se conservan consultas, inscripciones y sesiones futuras. El cierre automático sigue sus reglas habituales.</p>
            <form method="POST" action="{{ route('admin.offerings.state', [$offering, 'deactivate']) }}"><input type="hidden" name="_form" value="{{ hash('sha256', 'academic/offerings/administration.blade.php:1'.'|'.(route('admin.offerings.state', [$offering, 'deactivate']))) }}">@csrf
                <x-form-field :scope="hash('sha256', 'academic/offerings/administration.blade.php:1'.'|'.(route('admin.offerings.state', [$offering, 'deactivate'])))" name="reason" id="deactivate-reason" label="Motivo de desactivación" required minlength="5" maxlength="1000" />
                <button class="btn btn-outline-dark" @disabled(count($deactivationIssues) > 0)>Desactivar grupo {{ $offering->group_code }}</button>
            </form>
        </details>
    @else
        <form method="POST" action="{{ route('admin.offerings.state', [$offering, 'reactivate']) }}" class="mb-3"><input type="hidden" name="_form" value="{{ hash('sha256', 'academic/offerings/administration.blade.php:2'.'|'.(route('admin.offerings.state', [$offering, 'reactivate']))) }}">@csrf
            <p>Reactivar habilita el grupo y conserva el estado de sus ciclos, cómputos y recursos.</p>
            <x-form-field :scope="hash('sha256', 'academic/offerings/administration.blade.php:2'.'|'.(route('admin.offerings.state', [$offering, 'reactivate'])))" name="reason" id="reactivate-reason" label="Motivo de reactivación" required minlength="5" maxlength="1000" />
            <button class="btn btn-primary">Reactivar grupo {{ $offering->group_code }}</button>
        </form>
    @endif
    <details class="mb-3"><summary>Cerrar recursos vencidos</summary>
        <p class="mt-2">Cierra explícitamente portales vencidos y sus lectores, o lectores ya vencidos. No cierra ventanas vigentes ni finaliza sesiones.</p>
        <form method="POST" action="{{ route('admin.offerings.state', [$offering, 'expire-resources']) }}"><input type="hidden" name="_form" value="{{ hash('sha256', 'academic/offerings/administration.blade.php:3'.'|'.(route('admin.offerings.state', [$offering, 'expire-resources']))) }}">@csrf
            <x-form-field :scope="hash('sha256', 'academic/offerings/administration.blade.php:3'.'|'.(route('admin.offerings.state', [$offering, 'expire-resources'])))" name="reason" id="expire-reason" label="Motivo del cierre de recursos vencidos" required minlength="5" maxlength="1000" />
            <button class="btn btn-outline-dark">Cerrar recursos vencidos</button>
        </form>
    </details>
    <details><summary>Eliminar grupo vacío creado por error</summary>
        @if($deletionIssues)<div class="alert alert-info mt-2"><strong>Eliminación bloqueada.</strong><ul class="mb-0">@foreach($deletionIssues as $issue)<li>{{ $issue }}</li>@endforeach</ul></div>
        @else
        <p class="mt-2">Se eliminará {{ $offering->subject->code }} · {{ $offering->cycle->code }} · grupo {{ $offering->group_code }} (#{{ $offering->id }}). Esta acción es definitiva; la bitácora conserva su identificación y motivo.</p>
        <form method="POST" action="{{ route('admin.offerings.destroy', $offering) }}"><input type="hidden" name="_form" value="{{ hash('sha256', 'academic/offerings/administration.blade.php:4'.'|'.(route('admin.offerings.destroy', $offering))) }}">@csrf @method('DELETE')
            <x-form-field :scope="hash('sha256', 'academic/offerings/administration.blade.php:4'.'|'.(route('admin.offerings.destroy', $offering)))" name="confirmation" id="delete-confirmation" :label="'Escribe '.$offering->group_code.' para confirmar'" required maxlength="20" autocomplete="off" />
            <x-form-field :scope="hash('sha256', 'academic/offerings/administration.blade.php:4'.'|'.(route('admin.offerings.destroy', $offering)))" name="reason" id="delete-reason" label="Motivo de eliminación" required minlength="5" maxlength="1000" />
            <button class="btn btn-danger">Eliminar definitivamente el grupo {{ $offering->group_code }}</button>
        </form>
        @endif
    </details>
</div></section>
@endcan
