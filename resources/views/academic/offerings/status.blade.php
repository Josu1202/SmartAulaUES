<div class="d-flex flex-wrap gap-2 mb-3">
    @if($offering->cycle->status === 'closed')<span class="badge-soft">Ciclo cerrado</span>
    @elseif($offering->cycle->reopened_at)<span class="badge-soft">Ciclo en revisión</span>@endif
    @if(!$offering->active)<span class="badge-soft">Grupo desactivado</span>@endif
</div>
