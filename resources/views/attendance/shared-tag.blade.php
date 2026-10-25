@extends('layouts.app')
@section('title', 'Leer etiqueta compartida')
@section('content')
<x-page-heading title="Asistencia desde mi teléfono" subtitle="Lee la etiqueta compartida que preparó el docente para el registro vigente."/>
<div class="card"><div class="card-body" data-shared-tag-reader>
    <p>La lectura abre el registro de tu propia cuenta. Después se comprueba la ubicación cuando la clase es presencial. No necesitas una credencial personal.</p>
    <button class="btn btn-primary" type="button" data-read-shared-tag>Leer etiqueta compartida</button>
    <p role="status" aria-live="polite" data-shared-tag-status>Necesitas HTTPS y un navegador compatible con NFC.</p>
    <p>También puedes abrir el QR o enlace que comparta el docente. La lectura desde el navegador no permite convertir este teléfono en una tarjeta ni intercambiar credenciales entre teléfonos.</p>
    <a class="btn btn-outline-dark" href="{{ route('sessions.index') }}">Consultar mis sesiones</a>
</div></div>
@endsection
