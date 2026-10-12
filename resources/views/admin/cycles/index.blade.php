@extends('layouts.app')
@section('title', 'Ciclos académicos')
@section('content')
    <x-page-heading title="Ciclos académicos" subtitle="Consulta la organización de la oferta académica." />
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
                    </tr>@empty<tr>
                            <td colspan="5" class="text-secondary text-center py-5">No hay ciclos académicos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
