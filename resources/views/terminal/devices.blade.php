@extends('layouts.app')
@section('title', 'Terminales físicas')
@section('content')
<x-page-heading title="Terminales físicas" subtitle="Registra y administra el acceso de cada dispositivo." />
<form method="post" action="{{ route('admin.physical-terminals.create') }}" class="card card-body mb-4">
    @csrf
    <label for="terminal-name" class="form-label">Nombre</label>
    <input id="terminal-name" name="name" class="form-control mb-3" maxlength="80" required>
    <fieldset class="mb-3">
        <legend class="form-label">Capacidades</legend>
        @foreach (['nfc' => 'Lectura NFC', 'write' => 'Escritura NFC', 'qr' => 'QR corto'] as $value => $label)
            <label class="form-check"><input type="checkbox" name="capabilities[]" value="{{ $value }}" class="form-check-input">{{ $label }}</label>
        @endforeach
    </fieldset>
    <div><button class="btn btn-primary">Registrar terminal</button></div>
</form>
@foreach ($terminals as $terminal)
    <article class="card card-body mb-3">
        <h2 class="h4">{{ $terminal->name }}</h2>
        <p>{{ !$terminal->enabled ? 'Revocada' : ($terminal->last_seen_at && \Illuminate\Support\Carbon::parse($terminal->last_seen_at)->gt(now()->subSeconds(config('physical-terminal.offline_seconds'))) ? 'En línea' : 'Sin conexión') }}
            · {{ implode(', ', array_map(fn ($capability) => ['nfc' => 'Lectura NFC', 'write' => 'Escritura NFC', 'qr' => 'QR corto'][$capability], json_decode($terminal->capabilities, true))) }}</p>
        <p class="text-secondary">Última señal:
            {{ $terminal->last_seen_at ? \Illuminate\Support\Carbon::parse($terminal->last_seen_at)->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') : 'Sin señal' }}.
            Firmware {{ $terminal->firmware ?? 'sin informar' }}.</p>
        <form method="post" action="{{ route('admin.physical-terminals.rotate', $terminal->id) }}">
            @csrf
            <label class="form-label">Motivo <input name="reason" class="form-control" minlength="5" maxlength="255" required></label>
            <button class="btn btn-outline-dark">Rotar acceso</button>
            @if ($terminal->enabled)
                <button class="btn btn-outline-danger" formaction="{{ route('admin.physical-terminals.disable', $terminal->id) }}">Revocar acceso</button>
            @endif
        </form>
    </article>
@endforeach
{{ $terminals->links() }}
<div class="card card-body mt-4">
    <h2 class="h4">Operaciones recientes</h2>
    @forelse ($operations as $operation)
        @php($action = match ($operation->action) {
            'created' => 'Alta', 'rotated' => 'Acceso rotado', 'disabled' => 'Acceso revocado',
            'assigned' => 'Vinculada', 'released' => 'Liberada', 'write_queued' => 'Escritura preparada',
            'write_confirmed' => 'Escritura confirmada', 'write_cancelled' => 'Escritura cancelada',
            default => 'Escritura fallida',
        })
        <div>{{ $operation->name }} · {{ $operation->actor }} · {{ $action }} ·
            {{ \Illuminate\Support\Carbon::parse($operation->occurred_at)->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') }}</div>
    @empty
        <p class="text-secondary">Todavía no hay operaciones.</p>
    @endforelse
</div>
@endsection
