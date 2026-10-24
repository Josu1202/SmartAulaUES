@extends('layouts.app')
@section('title', 'Materias y grupos')
@section('content')
    <x-page-heading title="Materias y grupos" subtitle="Consulta el ciclo, el docente y las sesiones de tus grupos.">@can('create', \App\Models\Offering::class)<a class="btn btn-primary" href="{{ route('admin.offerings.create') }}">Crear grupo<x-icon name="arrow" /></a>@endcan</x-page-heading>
    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Clasificación de grupos">
        @foreach(['current' => 'Actuales', 'history' => 'Historial'] as $key => $label)
            <a class="btn {{ $filters['view'] === $key ? 'btn-primary' : 'btn-outline-dark' }}" href="{{ route('offerings.index', ['view' => $key, 'cycle_id' => $filters['cycle_id'], 'availability' => $filters['availability'] === 'inactive' ? 'inactive' : ($key === 'history' ? 'all' : 'active')]) }}" @if($filters['view'] === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('offerings.index') }}" class="card card-body mb-4">
        <input type="hidden" name="view" value="{{ $filters['view'] }}">
        <div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label" for="catalog-cycle">Ciclo académico</label><select class="form-select" id="catalog-cycle" name="cycle_id"><option value="">Todos los ciclos de esta vista</option>@foreach($cycles as $cycle)<option value="{{ $cycle->id }}" @selected($filters['cycle_id'] === $cycle->id)>{{ $cycle->code }}{{ $cycle->status === 'closed' ? ' · Cerrado' : ($cycle->reopened_at ? ' · En revisión' : '') }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label" for="catalog-availability">Estado del grupo</label><select class="form-select" id="catalog-availability" name="availability">@foreach(['active' => 'Habilitados', 'inactive' => 'Desactivados', 'all' => 'Todos los estados'] as $key => $label)<option value="{{ $key }}" @selected($filters['availability'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><button class="btn btn-outline-dark w-100">Aplicar filtros</button></div></div>
    </form>
    <p>{{ $groupCount }} grupos · {{ $enrollmentCount }} inscripciones de tu ámbito.</p>
    @if($filters['view'] === 'history')
        <p class="alert alert-info">Historial de ciclos cerrados. Los resultados cerrados se consultan en cada grupo desde su versión almacenada. Selecciona un ciclo para ver sus resúmenes operativos.</p>
        @if($statistics)
            <p class="text-secondary">Resúmenes operativos de los grupos filtrados: conteos de sesiones y registros conservados. Consulta los resultados de cierre en cada grupo.</p>
            @include('dashboard.operational-charts', ['showYears' => false])
        @endif
    @endif
    <div class="row g-4">
        @forelse($offerings as $offering)
            <div class="col-md-6">
                <article class="card offering-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-4"><span class="subject-icon"><x-icon
                                    name="book" /></span><span class="badge-soft">Grupo {{ $offering->group_code }}</span>
                        </div><small class="eyebrow">{{ $offering->subject->code }}</small>
                        <h2>{{ $offering->subject->name }}</h2>
                        <p class="text-secondary">{{ $offering->cycle->code }}</p>
                        @include('academic.offerings.status')
                        <div class="offering-meta">
                            <div><span>Docente</span><strong>{{ $offering->teacher->name }}</strong></div>
                            <div><span>Inscripciones / cupo</span><strong>{{ $offering->enrollments_count }} /
                                    {{ $offering->capacity }}</strong></div>
                        </div><a class="btn btn-outline-dark w-100 mt-4"
                            href="{{ route('offerings.show', ['offering' => $offering, ...request()->only(['view', 'cycle_id', 'availability']), 'catalog_page' => request('page')]) }}">Abrir grupo<x-icon name="arrow" /></a>
                    </div>
                </article>
        </div>@empty<div class="col-12">
                <div class="card empty-panel"><x-icon name="book" />
                    <h2>No hay grupos en esta vista</h2>
                    <p>Prueba otro ciclo o estado. Los grupos desactivados se consultan con el filtro Desactivados; los ciclos cerrados, en Historial.</p>
                </div>
            </div>
        @endforelse
    </div>
    <div class="mt-4">{{ $offerings->links() }}</div>
@endsection
