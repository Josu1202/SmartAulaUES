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
            <p class="text-secondary">Los PDF y CSV se generan desde el snapshot conservado. Si están pendientes, un worker debe procesar la cola «reports».</p>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Reportes por cómputo">
                <table class="table table-vcenter">
                    <thead><tr><th>Ciclo y materia</th><th>Grupo / Cómputo</th><th>Versión</th><th>Estado</th><th>PDF</th><th>CSV</th></tr></thead>
                    <tbody>
                        @forelse($versions as $version)
                            @php
                                $pdfRequest = $exportRequests->get($version->id.':PDF');
                                $csvRequest = $exportRequests->get($version->id.':CSV');

                                $pdfPending = $pdfRequest?->state === 'pending'
                                    && $pdfRequest->queued_at?->greaterThan(now()->subMinutes(10));
                                $pdfProcessing = $pdfRequest?->state === 'processing'
                                    && $pdfRequest->lease_expires_at?->greaterThan(now());

                                $csvPending = $csvRequest?->state === 'pending'
                                    && $csvRequest->queued_at?->greaterThan(now()->subMinutes(10));
                                $csvProcessing = $csvRequest?->state === 'processing'
                                    && $csvRequest->lease_expires_at?->greaterThan(now());
                            @endphp
                            <tr>
                                <td>{{ $version->period->offering->cycle->code }} · {{ $version->period->offering->subject->name }}</td>
                                <td>{{ $version->period->offering->group_code }} · Cómputo {{ $version->period->number }}</td>
                                <td>{{ $version->version }}</td>
                                <td>
                                    @if($version->period->state === 'closed' && $version->period->current_report_id === $version->id)
                                        Cierre vigente
                                    @elseif($version->period->state === 'open' && $version->period->current_report_id === $version->id)
                                        Cómputo reabierto · cierre conservado
                                    @else
                                        Versión histórica
                                    @endif
                                </td>
                                <td>
                                    @if($version->pdf_path !== null)
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('reports.download', $version) }}">Descargar PDF</a>
                                    @elseif($pdfPending)
                                        <span class="text-secondary" role="status">PDF pendiente</span>
                                    @elseif($pdfProcessing)
                                        <span class="text-secondary" role="status">Generando PDF</span>
                                    @else
                                        @if($pdfRequest?->state === 'failed')
                                            <span class="text-danger d-block">Falló la generación.</span>
                                        @elseif($pdfRequest !== null)
                                            <span class="text-secondary d-block">Solicitud anterior sin procesamiento activo.</span>
                                        @endif
                                        <form method="POST" action="{{ route('reports.generate', $version) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-dark" type="submit">{{ $pdfRequest === null ? 'Solicitar PDF' : 'Reintentar PDF' }}</button>
                                        </form>
                                    @endif
                                </td>
                                <td>
                                    @if($version->csv_path !== null)
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('reports.csv.download', $version) }}">Descargar CSV</a>
                                    @elseif($csvPending)
                                        <span class="text-secondary" role="status">CSV pendiente</span>
                                    @elseif($csvProcessing)
                                        <span class="text-secondary" role="status">Generando CSV</span>
                                    @else
                                        @if($csvRequest?->state === 'failed')
                                            <span class="text-danger d-block">Falló la generación.</span>
                                        @elseif($csvRequest !== null)
                                            <span class="text-secondary d-block">Solicitud anterior sin procesamiento activo.</span>
                                        @endif
                                        <form method="POST" action="{{ route('reports.csv.generate', $version) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-dark" type="submit">{{ $csvRequest === null ? 'Solicitar CSV' : 'Reintentar CSV' }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No hay versiones de cierre disponibles para esta cuenta.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $versions->links() }}
        </div>
    </section>
@endsection
