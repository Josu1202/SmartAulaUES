@extends('layouts.app')

@section('title', 'Noticia académica')

@section('content')
<x-page-heading
    title="Noticia académica"
    subtitle="Contenido restringido a la audiencia autorizada."
/>

@if(session('status'))
    <div class="alert alert-success" role="status">
        {{ session('status') }}
    </div>
@endif

<article class="card mb-3">
    <div class="card-body">
        <h1 class="h2">{{ $news->title }}</h1>

        <p class="text-secondary">
            {{ $news->offering ? $news->offering->subject->name.' · Grupo '.$news->offering->group_code : 'Ciclo '.$news->cycle?->code }}

            · {{ $news->published_at?->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') ?? 'Borrador' }}

            · {{ $news->author->name }}

            @if($news->status === 'draft')
                · Borrador
            @elseif($news->status === 'archived')
                · Archivada
            @endif
        </p>

        <div style="white-space: pre-wrap; overflow-wrap: anywhere">
            {{ $news->content }}
        </div>
    </div>
</article>

<div class="d-flex gap-2 flex-wrap">
    @can('update', $news)
        <a class="btn btn-outline-dark" href="{{ route('news.edit', $news) }}">
            Editar
        </a>
    @endcan

    @can('publish', $news)
        <form method="POST" action="{{ route('news.publish', $news) }}">
            @csrf
            <button class="btn btn-primary" type="submit">
                Publicar noticia
            </button>
        </form>
    @endcan

    @can('archive', $news)
        <form method="POST" action="{{ route('news.archive', $news) }}">
            @csrf
            <button class="btn btn-outline-dark" type="submit">
                Archivar noticia
            </button>
        </form>
    @endcan

    <a class="btn btn-outline-dark" href="{{ route('news.index') }}">
        Volver
    </a>
</div>
@endsection
