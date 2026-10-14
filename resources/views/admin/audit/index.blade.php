@extends('layouts.app')
@section('title', 'Bitácora de cambios')
@section('content')
    <x-page-heading title="Bitácora de cambios" subtitle="Quién realizó cada cambio, cuándo y con qué motivo." eyebrow="ADMINISTRACIÓN" />
    <div class="card"><form class="filter-bar" method="GET"><div><label class="visually-hidden" for="entity">Tipo de registro</label><select class="form-select" id="entity" name="entity"><option value="">Todos los registros</option>@foreach($entityLabels as $key => $caption)<option value="{{ $key }}" @selected(request('entity') === $key)>{{ $caption }}</option>@endforeach</select></div><button class="btn btn-primary">Filtrar</button><a class="btn btn-outline-secondary" href="{{ route('admin.audit.index') }}">Limpiar</a></form>
    <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Fecha</th><th>Responsable</th><th>Cambio</th><th>Registro</th><th>Detalle</th></tr></thead><tbody>
        @forelse($logs as $log)<tr><td class="text-nowrap">{{ $log->created_at->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}</td><td>{{ $log->actor?->name ?? 'Sistema' }}</td><td>{{ __('audit.'.$log->action, [], 'es') }}</td><td>{{ $entityLabels[$log->entity_type] ?? $log->entity_type }} <small class="text-secondary">#{{ $log->entity_id }}</small></td><td class="audit-detail"><div>{{ $log->reason ?? 'Sin motivo adicional' }}</div><details class="mt-2"><summary>Ver valores</summary><div class="audit-values"><strong>Antes</strong><pre>{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre><strong>Después</strong><pre>{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></details></td></tr>
        @empty<tr><td colspan="5" class="text-center text-secondary py-5">Los cambios aparecerán aquí cuando comiences a administrar los datos.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $logs->links() }}</div></div>
@endsection
