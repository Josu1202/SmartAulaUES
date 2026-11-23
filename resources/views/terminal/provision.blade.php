@extends('layouts.app')
@section('title', 'Configurar terminal')
@section('content')
<x-page-heading title="Configurar terminal" subtitle="Copia el secreto directamente a la configuración privada del dispositivo. Esta pantalla lo muestra una sola vez." />
<div class="card card-body">
    <p>Identificador <code>{{ $id }}</code></p>
    <p class="text-break">Secreto <code>{{ $secret }}</code></p>
    <div><a class="btn btn-primary" href="{{ route('admin.physical-terminals.index') }}">Volver a terminales</a></div>
</div>
@endsection
