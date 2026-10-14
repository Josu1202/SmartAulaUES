@extends('layouts.app')
@section('title', 'Materias y grupos')
@section('content')
    <x-page-heading title="Materias y grupos" subtitle="Consulta el ciclo, el docente y las sesiones de tus grupos.">@can('create', \App\Models\Offering::class)<a class="btn btn-primary" href="{{ route('admin.offerings.create') }}">Crear grupo<x-icon name="arrow" /></a>@endcan</x-page-heading>
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
                        <div class="offering-meta">
                            <div><span>Docente</span><strong>{{ $offering->teacher->name }}</strong></div>
                            <div><span>Inscripciones / cupo</span><strong>{{ $offering->enrollments_count }} /
                                    {{ $offering->capacity }}</strong></div>
                        </div><a class="btn btn-outline-dark w-100 mt-4"
                            href="{{ route('offerings.show', $offering) }}">Abrir grupo<x-icon name="arrow" /></a>
                    </div>
                </article>
        </div>@empty<div class="col-12">
                <div class="card empty-panel"><x-icon name="book" />
                    <h2>No hay grupos asignados</h2>
                    <p>Coordinación podrá asignar los grupos correspondientes a tu cuenta.</p>
                </div>
            </div>
        @endforelse
    </div>
    <div class="mt-4">{{ $offerings->links() }}</div>
@endsection
