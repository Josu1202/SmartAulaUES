@extends('layouts.app')
@section('title', $definition['title'])
@section('content')
    <x-page-heading :title="$definition['title']" :subtitle="$definition['description']" />
    <div class="card empty-panel"><span class="pending-icon"><x-icon :name="$definition['icon']" /></span><span
            class="badge-soft">Próxima versión</span>
        <h2>Este módulo está en preparación</h2>
        <p>La base del sistema ya está disponible. Esta función se habilitará en una siguiente etapa de desarrollo.</p><a
            class="btn btn-outline-dark" href="{{ route('dashboard') }}">Volver al inicio<x-icon name="arrow" /></a>
    </div>
@endsection
