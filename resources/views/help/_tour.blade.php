@php
    $helpCatalog = app(\App\Services\Help\TutorialCatalog::class);
    $helpProgress = app(\App\Services\Help\TutorialProgressService::class);
    $helpSteps = $helpCatalog->forRole(auth()->user()->role);
    $helpAutoOffer = ! auth()->user()->must_change_password && $helpProgress->shouldOffer(auth()->user());
@endphp

<dialog class="help-tour-dialog" data-help-tour {{ $helpAutoOffer ? 'data-tour-auto' : '' }}
    aria-labelledby="tour-heading" aria-describedby="tour-description">
    <div class="help-tour-shell">
        <header class="help-tour-header">
            <div>
                <span class="eyebrow">SMARTAULAUES · GUÍA POR PERFIL</span>
                <h2 class="h3" id="tour-heading" tabindex="-1">Conozca su espacio académico</h2>
                <p class="text-secondary mb-0" id="tour-description">Recorrido de {{ auth()->user()->role->label() }}. Puede omitirlo o cerrarlo con Escape.</p>
            </div>
            <form method="POST" action="{{ route('help.tour.skip') }}" data-tour-skip-form>
                @csrf
                <button type="submit" class="btn btn-outline-dark help-tour-close" aria-label="Cerrar y omitir recorrido">
                    <x-icon name="close" />
                </button>
            </form>
        </header>

        <p class="help-tour-counter" role="status" aria-live="polite" data-tour-counter> Paso 1 de {{ count($helpSteps) }} </p>

        <div class="help-tour-steps">
            @foreach ($helpSteps as $step)
                <section class="help-tour-step" data-tour-step {{ $loop->first ? '' : 'hidden' }} aria-label="Paso {{ $loop->iteration }}: {{ $step['title'] }}">
                    <div class="help-tour-icon"><x-icon :name="$step['icon']" /></div>
                    <h3 class="h4">{{ $step['title'] }}</h3>
                    <p>{{ $step['description'] }}</p>
                    <p class="help-tour-route-label">Puede encontrar esta sección desde el menú de la aplicación.</p>
                </section>
            @endforeach
        </div>

        <div class="help-tour-actions">
            <button class="btn btn-outline-dark" type="button" data-tour-back disabled>Anterior</button>
            <button class="btn btn-primary" type="button" data-tour-next>Siguiente</button>
            <form method="POST" action="{{ route('help.tour.complete') }}" data-tour-complete-form hidden>
                @csrf
                <button class="btn btn-primary" type="submit">Finalizar recorrido</button>
            </form>
            <form method="POST" action="{{ route('help.tour.skip') }}" data-tour-skip-form>
                @csrf
                <button class="btn btn-outline-dark" type="submit">Omitir</button>
            </form>
        </div>
        <p class="help-tour-hint">Para ver estos pasos de nuevo, abra Ayuda en el menú.</p>
    </div>
</dialog>
