@extends('layouts.app')
@section('title', 'Escritura NFC en terminal')
@section('content')
<a class="back-link" href="{{ route('admin.credentials.index') }}">← Credenciales NFC</a>
<x-page-heading title="Escritura NFC en terminal" subtitle="La credencial se activa cuando la terminal vuelve a leer la etiqueta y el servidor confirma." />
<form method="get" class="mb-3">
    <label class="form-label">Buscar por nombre o carné <input name="q" class="form-control" maxlength="80" value="{{ request('q') }}"></label>
    <button class="btn btn-outline-dark">Buscar</button>
</form>
@if ($terminals->isNotEmpty() && $students->isNotEmpty())
    <form method="post" action="{{ route('admin.terminal-jobs.issue') }}" class="card card-body mb-3">
        @csrf
        <label for="write-student" class="form-label">Estudiante</label>
        <select id="write-student" name="student_id" class="form-select mb-3" required>
            @foreach ($students as $student)
                <option value="{{ $student->id }}">{{ $student->student_code }} · {{ $student->name }}</option>
            @endforeach
        </select>
        <label for="write-terminal" class="form-label">Terminal conectada</label>
        <select id="write-terminal" name="terminal_id" class="form-select mb-3" required>
            @foreach ($terminals as $terminal)
                <option value="{{ $terminal->id }}">{{ $terminal->name }}</option>
            @endforeach
        </select>
        <div><button class="btn btn-primary">Preparar etiqueta</button></div>
    </form>
@else
    <p role="status">Se necesita una terminal conectada con escritura NFC y un estudiante activo.</p>
@endif
{{ $students->links() }}
<h2 class="h4 mt-4">Trabajos de escritura</h2>
<a class="btn btn-outline-dark mb-3" href="{{ request()->fullUrl() }}">Actualizar estado</a>
@foreach ($jobs as $job)
    @php
        $expired = \Illuminate\Support\Carbon::parse($job->expires_at)->lte(now());
        $leased = $job->lease_until && \Illuminate\Support\Carbon::parse($job->lease_until)->gt(now());
        $state = match ($job->state) {
            'pending' => $expired ? 'Vencida' : ($job->credential_state !== 'pending' ? 'Alta sustituida o revocada' : ($leased ? 'En terminal' : 'Esperando terminal')),
            'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'incompatible' => 'Etiqueta incompatible',
            'write_error' => 'Error de escritura', 'readback_error' => 'Error de lectura posterior', 'timeout' => 'Tiempo agotado',
            default => 'Vencida',
        };
    @endphp
    <article class="card card-body mb-3">
        <p>{{ $job->student }} · {{ $job->terminal }} · {{ $state }}</p>
        @if ($job->state === 'pending' && !$expired)
            <form method="post" action="{{ route('admin.terminal-jobs.cancel', $job->id) }}">
                @csrf
                @method('DELETE')
                <label class="form-label">Motivo <input name="reason" class="form-control" minlength="5" maxlength="255" required></label>
                <button class="btn btn-outline-danger">Cancelar escritura</button>
            </form>
        @endif
    </article>
@endforeach
{{ $jobs->links() }}
@endsection
