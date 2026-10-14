@extends('layouts.app')
@section('title', 'Solicitar permiso')
@section('content')
<a class="back-link" href="{{ route('sessions.show', $session) }}">← Sesión</a><x-page-heading title="Solicitar permiso" :subtitle="$session->offering->subject->name.' · '.$session->starts_at->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i')"/>
<div class="card"><div class="card-body"><form method="POST" enctype="multipart/form-data" action="{{ route('requests.store', $session) }}">@csrf<x-form-field name="reason" label="Motivo de la solicitud" type="textarea" required minlength="10" maxlength="2000"/><x-form-field name="evidence" label="Evidencia opcional" type="file" accept=".pdf,.jpg,.jpeg,.png" help="PDF, JPG o PNG, hasta 5 MB. Acceso privado para el titular, su docente y coordinación."/><p class="form-hint">La solicitud no registra presencia. El docente decide y el resultado queda auditado.</p><button class="btn btn-primary">Enviar solicitud</button></form></div></div>
@endsection
