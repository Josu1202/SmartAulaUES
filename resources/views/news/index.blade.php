@extends('layouts.app')

@section('title', 'Noticias académicas')

@section('content')
<x-page-heading title="Noticias académicas" subtitle="Publicaciones de tus grupos y ciclos autorizados." />

@if(session('status'))
    <div class="alert alert-success" role="status">
        {{ session('status') }}
    </div>
@endif

@can('create', \App\Models\News::class)
    <p>
        <a class="btn btn-primary" href="{{ route('news.create') }}">
            Redactar noticia
        </a>
    </p>
@endcan

@forelse($news as $item)
    <article class="card mb-3">
        <div class="card-body">
            <h2 class="h3">
                <a href="{{ route('news.show', $item) }}">
                    {{ $item->title }}
                </a>
            </h2>

            <p class="text-secondary">
                {{ $item->offering ? $item->offering->subject->name.' · Grupo '.$item->offering->group_code : 'Ciclo '.$item->cycle?->code }}

                · {{ $item->published_at?->timezone(config('smartaulaues.display_timezone'))->format('d/m/Y H:i') ?? 'Sin publicar' }}

                @if($item->status === 'draft')
                    · Borrador
                @elseif($item->status === 'archived')
                    · Archivada
                @endif
            </p>

            <p>{{ \Illuminate\Support\Str::limit($item->content, 260) }}</p>

            <a class="btn btn-sm btn-outline-dark" href="{{ route('news.show', $item) }}">
                Leer noticia
            </a>
        </div>
    </article>
@empty
    <div class="card card-body">
        <h2 class="h4">Sin noticias visibles</h2>
        <p class="text-secondary">
            Las publicaciones autorizadas aparecerán aquí.
        </p>
    </div>
@endforelse

{{ $news->links() }}
@endsection
