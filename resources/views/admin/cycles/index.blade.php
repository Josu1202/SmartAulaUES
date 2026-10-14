@extends('layouts.app')
@section('title', 'Ciclos académicos')
@section('content')
    <x-page-heading title="Ciclos académicos" subtitle="Define las fechas y el estado de cada ciclo." eyebrow="ORGANIZACIÓN ACADÉMICA"><a class="btn btn-primary" href="{{ route('admin.cycles.create') }}">Crear ciclo<x-icon name="arrow" /></a></x-page-heading>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Ciclo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Grupos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cycles as $cycle)
                        <tr>
                            <td><strong>{{ $cycle->code }}</strong></td>
                            <td>{{ $cycle->starts_on->format('d/m/Y') }}</td>
                            <td>{{ $cycle->ends_on->format('d/m/Y') }}</td>
                            <td>{{ $cycle->offerings_count }}</td>
                            <td><span
                                    class="badge-soft">{{ ['planned' => 'Planificado', 'active' => 'Activo', 'closed' => 'Cerrado'][$cycle->status] }}</span>
                            </td>
                            <td><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.cycles.edit', $cycle) }}">{{ $cycle->status === 'closed' ? 'Consultar' : 'Editar' }}</a></td>
                    </tr>@empty<tr>
                            <td colspan="6" class="text-secondary text-center py-5">No hay ciclos académicos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
