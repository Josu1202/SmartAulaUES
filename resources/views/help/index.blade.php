@extends('layouts.app')

@section('title', 'Ayuda')

@section('content')
    <x-page-heading title="Ayuda y recorrido guiado" subtitle="Conozca las funciones disponibles para su perfil. Puede repetir el recorrido cuando lo necesite." />

    <section class="card mb-4" aria-labelledby="help-tour-heading">
        <div class="card-body">
            <h2 class="h3" id="help-tour-heading">Recorrido de {{ auth()->user()->role->label() }}</h2>
            <p class="text-secondary">Versión {{ $version }} · {{ match ($status) { 'completed' => 'Completado', 'skipped' => 'Omitido', default => 'Aún no realizado' } }}</p>
            <p>Este recorrido no solicita permisos de ubicación, cámara ni NFC. Puede cerrarlo con Escape o elegir Omitir.</p>
            <button class="btn btn-primary" type="button" data-tour-open>
                <x-icon name="help" /> Repetir recorrido
            </button>
            <p class="form-hint">Si no aparece el recorrido, use los enlaces de la guía que se muestran a continuación.</p>
        </div>
    </section>

    <section class="card" aria-labelledby="help-steps-heading">
        <div class="card-header"><h2 class="card-title" id="help-steps-heading">Guía rápida para su perfil</h2></div>
        <div class="card-body">
            <ol class="help-step-list">
                @foreach ($steps as $step)
                    <li>
                        <div class="help-step-content">
                            <h3 class="h4"><x-icon :name="$step['icon']" /> {{ $step['title'] }}</h3>
                            <p>{{ $step['description'] }}</p>
                            <a href="{{ route($step['route']) }}">Abrir {{ $step['title'] }}</a>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endsection
