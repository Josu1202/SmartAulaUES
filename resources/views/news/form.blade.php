@extends('layouts.app')

@section('title', $news->exists ? 'Editar noticia' : 'Crear noticia')

@section('content')
<x-page-heading
    :title="$news->exists ? 'Editar noticia' : 'Crear noticia'"
    subtitle="El contenido se muestra como texto seguro, sin HTML activo."
/>

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form class="card card-body" method="POST" action="{{ $news->exists ? route('news.update', $news) : route('news.store') }}">
    @csrf

    @if($news->exists)
        @method('PUT')
    @endif

    <div class="mb-3">
        <label class="form-label" for="news-title">Título</label>
        <input id="news-title" type="text" name="title" class="form-control" maxlength="180" required value="{{ old('title', $news->title) }}">
    </div>

    <div class="mb-3">
        <label class="form-label" for="news-content">Contenido</label>
        <textarea id="news-content" name="content" class="form-control" rows="9" maxlength="10000" required>{{ old('content', $news->content) }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label" for="news-group">Audiencia: grupo</label>
        <select id="news-group" class="form-select" name="offering_id">
            <option value="">Ninguno (si selecciona ciclo)</option>

            @foreach($groups as $group)
                <option value="{{ $group->id }}" @selected((int) old('offering_id', $news->offering_id) === $group->id)>
                    {{ $group->subject->name }} · {{ $group->group_code }}
                </option>
            @endforeach
        </select>
    </div>

    @if($cycles->isNotEmpty())
        <div class="mb-3">
            <label class="form-label" for="news-cycle">Audiencia: ciclo (Coordinación)</label>
            <select id="news-cycle" name="cycle_id" class="form-select">
                <option value="">Ninguno (si selecciona grupo)</option>

                @foreach($cycles as $cycle)
                    <option value="{{ $cycle->id }}" @selected((int) old('cycle_id', $news->cycle_id) === $cycle->id)>
                        {{ $cycle->code }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <p class="text-secondary">
        Seleccione exactamente un grupo o un ciclo.
        Primero se guarda un borrador; publicar requiere una acción separada.
    </p>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-outline-dark" href="{{ route('news.index') }}">Cancelar</a>
    </div>
</form>
@endsection
