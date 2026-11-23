@extends('layouts.app')
@section('title', 'Vincular terminal física')
@section('content')
<a class="back-link" href="{{ route('sessions.show', $session) }}">← Sesión</a>
<x-page-heading title="Terminal física" :subtitle="$session->offering->subject->name" />
<p>Abre el portal en la sesión y habilita NFC o QR según el dispositivo. El QR de proyección se sigue compartiendo desde la sesión.</p>
@if ($portals->isNotEmpty() && $terminals->isNotEmpty())
    <form method="post" action="{{ route('sessions.physical-terminal.assign', $session) }}" class="card card-body mb-4">
        @csrf
        <label for="terminal-id" class="form-label">Terminal en línea</label>
        <select id="terminal-id" name="terminal_id" class="form-select mb-3" required>
            @foreach ($terminals as $terminal)
                <option value="{{ $terminal->id }}">{{ $terminal->name }} · {{ implode(', ', array_map(fn ($capability) => ['nfc' => 'NFC', 'write' => 'Escritura NFC', 'qr' => 'QR corto'][$capability], json_decode($terminal->capabilities, true))) }}</option>
            @endforeach
        </select>
        <label for="terminal-portal" class="form-label">Portal</label>
        <select id="terminal-portal" name="portal_id" class="form-select mb-3" required>
            @foreach ($portals as $portal)
                <option value="{{ $portal->id }}">Hasta {{ $portal->expires_at->timezone(config('smartaulaues.display_timezone'))->format('H:i') }} · {{ implode(', ', $portal->enabled_methods) }}</option>
            @endforeach
        </select>
        <fieldset class="mb-3">
            <legend class="form-label">Métodos físicos</legend>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="methods[]" value="nfc"> NFC individual</label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="methods[]" value="qr"> QR corto en la OLED</label>
        </fieldset>
        <div><button class="btn btn-primary">Vincular</button></div>
    </form>
@else
    <p role="status">Se necesita un portal vigente y una terminal conectada.</p>
@endif
@foreach ($assignments as $assignment)
    <form method="post" action="{{ route('sessions.physical-terminal.release', [$session, $assignment->id]) }}" class="card card-body mb-3">
        @csrf
        @method('DELETE')
        <p>{{ $assignment->name }} · Hasta {{ \Illuminate\Support\Carbon::parse($assignment->expires_at)->timezone(config('smartaulaues.display_timezone'))->format('H:i') }}</p>
        <div><button class="btn btn-outline-dark">Liberar terminal</button></div>
    </form>
@endforeach
@endsection
