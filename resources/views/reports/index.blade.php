@extends('layouts.app')
@section('title', 'Reportes de cómputos')
@section('content')
    <x-page-heading title="Reportes de cómputos" subtitle="Descargue versiones de cierre de los grupos que puede consultar." />
    @if(session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    <section class="card mb-4" aria-labelledby="reports-heading">
        <div class="card-header"><h2 class="card-title" id="reports-heading">Versiones certificadas</h2></div>
        <div class="card-body">
            <p class="text-secondary">Los PDF se generan desde el snapshot conservado. Si están pendientes, un worker debe procesar la cola «reports».</p>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Reportes por cómputo">
                <table class="table table-vcenter">
                    <thead><tr><th>Ciclo y materia</th><th>Grupo / Cómputo</th><th>Versión</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @forelse($versions as $version)
                            <tr>
                                <td>{{ $version->period->offering->cycle->code }} · {{ $version->period->offering->subject->name }}</td>
                                <td>{{ $version->period->offering->group_code }} · Cómputo {{ $version->period->number }}</td>
                                <td>{{ $version->version }}</td>
                                <td>{{ $version->period->state === 'closed' && $version->period->current_report_id === $version->id ? 'Cierre vigente' : 'Versión histórica' }}</td>
                                <td>
                                    @if($version->pdf_path !== null)
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('reports.download', $version) }}">Descargar PDF</a>
                                    @else
                                        <form method="POST" action="{{ route('reports.generate', $version) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-dark" type="submit">Solicitar PDF</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No hay versiones de cierre disponibles para esta cuenta.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $versions->links() }}
        </div>
    </section>
@endsection
