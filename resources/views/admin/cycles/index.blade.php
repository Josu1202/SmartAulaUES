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
                            <td><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.cycles.edit', $cycle) }}">{{ $cycle->status === 'closed' ? 'Consultar' : 'Editar' }}</a>
                                @if($cycle->reopened_at && $cycle->status !== 'closed')<p class="mt-2">En revisión · cierre manual requerido.</p>@endif
                                @if($cycle->closed_at)<p class="mt-2">Cerrado el {{ $cycle->closed_at->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}</p>@endif
                                @if($cycle->closure_issues)<details class="mt-2"><summary>Impedimentos del último intento</summary><ul class="mt-2">@foreach($cycle->closure_issues as $issue)<li>{{ $issue }}</li>@endforeach</ul><a href="{{ route('offerings.index') }}">Consultar grupos</a></details>@endif
                                @if($cycle->status === 'closed')
                                    <details class="mt-2"><summary>Reabrir ciclo</summary><form method="POST" action="{{ route('admin.cycles.reopen', $cycle) }}" class="mt-2">@csrf<x-form-field name="reason" :id="'cycle-reopen-'.$cycle->id" label="Motivo de reapertura" required minlength="5" maxlength="1000" /><button class="btn btn-sm btn-outline-dark">Reabrir ciclo</button></form></details>
                                @elseif($cycle->ends_on->toDateString() < now('America/El_Salvador')->toDateString())
                                    <form method="POST" action="{{ route('admin.cycles.close', $cycle) }}" class="mt-2">@csrf<button class="btn btn-sm btn-primary">Validar y cerrar ciclo</button></form>
                                @endif
                            </td>
                    </tr>@empty<tr>
                            <td colspan="6" class="text-secondary text-center py-5">No hay ciclos académicos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
