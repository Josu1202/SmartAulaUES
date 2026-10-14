@extends('layouts.app')
@section('title', 'Catálogo de materias')
@section('content')
    <x-page-heading title="Catálogo de materias" subtitle="Define las materias que podrán ofrecerse en cada ciclo." eyebrow="ORGANIZACIÓN ACADÉMICA"><a class="btn btn-primary" href="{{ route('admin.subjects.create') }}">Crear materia<x-icon name="arrow" /></a></x-page-heading>
    <div class="card">
        <form class="filter-bar" method="GET"><div class="filter-search"><label class="visually-hidden" for="q">Buscar materia</label><x-icon name="search" /><input class="form-control" id="q" name="q" value="{{ request('q') }}" maxlength="80" placeholder="Nombre o código de materia"></div><button class="btn btn-primary">Buscar</button><a class="btn btn-outline-secondary" href="{{ route('admin.subjects.index') }}">Limpiar</a></form>
        <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Materia</th><th>Código</th><th>Grupos</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
            @forelse($subjects as $subject)<tr><td><strong>{{ $subject->name }}</strong></td><td>{{ $subject->code }}</td><td>{{ $subject->offerings_count }}</td><td><span class="badge-soft {{ $subject->active ? '' : 'badge-muted' }}">{{ $subject->active ? 'Activa' : 'Inactiva' }}</span></td><td><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.subjects.edit', $subject) }}">Editar</a></td></tr>
            @empty<tr><td colspan="5" class="text-center text-secondary py-5">No hay materias. Crea la primera para ofrecer un grupo.</td></tr>@endforelse
        </tbody></table></div><div class="card-footer">{{ $subjects->links() }}</div>
    </div>
@endsection
