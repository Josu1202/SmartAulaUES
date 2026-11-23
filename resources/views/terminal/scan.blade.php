@extends('layouts.app')
@section('title', 'Escanear QR de terminal')
@section('content')
<x-page-heading title="Escanear QR de terminal" subtitle="Acerca la cámara al código de la pantalla física y mantén el teléfono firme hasta enfocar." />
<section class="card card-body" data-terminal-scan data-resolve="{{ route('terminal.qr.resolve') }}"
    data-store="{{ route('terminal.qr.store') }}" @include('attendance.geolocation-options')>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" data-camera>Activar cámara</button>
        <button type="button" class="btn btn-outline-dark" data-reset>Cancelar</button>
        <button type="button" class="btn btn-primary" data-confirm hidden>Confirmar asistencia</button>
    </div>
    <video muted playsinline class="w-100 mt-3" style="max-width: 32rem" hidden></video>
    <p class="live-status mt-3" role="status" data-status>Autoriza el acceso a la cámara cuando el navegador lo solicite.</p>
    <form data-manual autocomplete="off">
        <label class="form-label" for="terminal-short-code">Introducir token de 20 caracteres</label>
        <div class="d-flex flex-wrap gap-2">
            <input id="terminal-short-code" class="form-control w-auto" pattern="[A-Z2-7]{20}" maxlength="20" spellcheck="false" required>
            <button class="btn btn-outline-dark">Consultar código</button>
        </div>
    </form>
</section>
@endsection
