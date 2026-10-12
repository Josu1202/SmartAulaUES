@props(['title', 'subtitle' => '', 'eyebrow' => 'ESPACIO ACADÉMICO'])
<div class="page-heading">
    <div><span class="eyebrow">{{ $eyebrow }}</span>
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>{{ $slot }}
</div>
