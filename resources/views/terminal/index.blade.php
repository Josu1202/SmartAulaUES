@extends('layouts.app')
@section('title', 'Terminal NFC')
@section('content')
    <x-page-heading title="Terminal NFC"
        subtitle="Abre una clase en curso para registrar asistencias o utiliza el diagnóstico del dispositivo." />
    <div class="card mb-4"><div class="card-header"><h2 class="card-title">Clases en curso</h2></div><div class="card-body">@forelse($sessions as $session)<a class="btn btn-outline-dark me-2 mb-2" href="{{ route('sessions.show', $session) }}">{{ $session->offering->subject->name }} · Grupo {{ $session->offering->group_code }}</a>@empty<p class="text-secondary mb-0">Inicia una sesión desde tu grupo para abrir el registro y el lector de la clase.</p>@endforelse</div></div>
    <div class="terminal-grid">
        <section class="card terminal-card" data-nfc-diagnostic>
            <div class="card-body"><span class="badge-soft">Prueba de lectura</span>
                <div class="nfc-target"><x-icon name="nfc" /></div>
                <h2>Acerca tu etiqueta</h2>
                <p class="text-secondary">Usa Android con NFC habilitado y Chrome, desde una dirección HTTPS.</p>
                <div class="nfc-status" data-nfc-status role="status" aria-live="polite">Comprobando compatibilidad…</div>
                <button type="button" class="btn btn-primary btn-lg w-100" data-nfc-start><x-icon name="nfc" />Iniciar
                    lector</button><button type="button" class="btn btn-outline-dark w-100 mt-2" data-nfc-stop
                    disabled>Detener lector</button>
            </div>
            <div class="card-bottom-note"><x-icon name="info" />Esta prueba no registra asistencias ni asigna etiquetas.
            </div>
        </section>
        <section class="card">
            <div class="card-body"><span class="eyebrow">ANTES DE COMENZAR</span>
                <h2>Una prueba con tu dispositivo</h2>
                <ol class="terminal-checklist">
                    <li><span>01</span>
                        <div><strong>Prepara el celular</strong>
                            <p>Enciende NFC y mantén la pantalla desbloqueada.</p>
                        </div>
                    </li>
                    <li><span>02</span>
                        <div><strong>Permite el acceso</strong>
                            <p>Pulsa «Iniciar lector» y acepta el permiso del navegador.</p>
                        </div>
                    </li>
                    <li><span>03</span>
                        <div><strong>Acerca una etiqueta NDEF</strong>
                            <p>Una etiqueta con el formato {{ config('smartaulaues.name') }} se reconocerá como tal. Otros contenidos se identificarán
                                sin mostrarlos.</p>
                        </div>
                    </li>
                </ol>
                <div class="terminal-note">La lectura y el registro de asistencia son pasos distintos. La conexión al
                    registro se incorporará en una próxima versión.</div>
            </div>
        </section>
    </div>
@endsection
