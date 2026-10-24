@extends('layouts.app')
@php
    $labels = ['cycle' => 'ciclo académico', 'subject' => 'materia', 'offering' => 'grupo', 'period' => 'cómputo', 'enrollment' => 'inscripción'];
    $plural = ['cycle' => 'cycles', 'subject' => 'subjects', 'offering' => 'offerings', 'period' => 'periods', 'enrollment' => 'enrollments'];
    $nested = in_array($kind, ['period', 'enrollment']);
    $prefix = $nested ? 'admin.offerings.'.$plural[$kind] : 'admin.'.$plural[$kind];
    $parameters = $nested ? ($record->exists ? [$offering, $record] : [$offering]) : ($record->exists ? [$record] : []);
    $action = route($prefix.($record->exists ? '.update' : '.store'), $parameters);
    $back = $nested ? route('offerings.show', $offering) : ($kind === 'offering' ? route('offerings.index') : route($prefix.'.index'));
    $title = ($record->exists ? 'Editar ' : 'Crear ').$labels[$kind];
    $readOnly = ($nested && !$offering->active) || ($kind === 'offering' && $record->exists && !$record->active) || ($record->exists && $kind === 'cycle' && $record->status === 'closed') || ($kind === 'period' && isset($offering) && $offering->cycle_format_id !== null) || ($record->exists && $kind === 'period' && $record->state !== 'open') || ($nested && $offering->cycle->status === 'closed') || ($record->exists && $kind === 'offering' && $record->cycle->status === 'closed');
@endphp
@section('title', $title)
@section('content')
    <a class="back-link" href="{{ $back }}">← Volver</a>
    <x-page-heading :title="$title" :subtitle="$nested ? $offering->subject->name.' · '.$offering->cycle->code.' · Grupo '.$offering->group_code : 'Organiza la oferta académica y conserva su historial.'" eyebrow="ORGANIZACIÓN ACADÉMICA" />
    @if($readOnly)<div class="alert alert-info">Consulta de solo lectura: grupo desactivado, calendario heredado o ciclo/cómputo cerrado. Para cambios académicos, reactiva y reabre lo que corresponda.</div>@endif
    <div class="row g-4"><div class="col-lg-8"><div class="card"><div class="card-body">
        <form method="POST" action="{{ $action }}">@csrf @if($record->exists) @method('PUT') @endif
            <fieldset @disabled($readOnly)>
                @if(in_array($kind, ['cycle', 'subject']))
                    <x-form-field name="code" label="Código" :value="$record->code" required maxlength="30" help="Se guarda en mayúsculas y debe ser único." />
                @endif
                @if($kind === 'cycle')
                    <div class="row"><div class="col-md-6"><x-form-field name="starts_on" label="Fecha inicial" type="date" :value="$record->starts_on?->toDateString()" required /></div><div class="col-md-6"><x-form-field name="ends_on" label="Fecha final" type="date" :value="$record->ends_on?->toDateString()" required /></div></div>
                    <x-form-field name="status" label="Estado" :value="$record->status" :options="$record->status === 'closed' ? ['closed' => 'Cerrado'] : ($record->reopened_at ? ['active' => 'Activo · en revisión'] : ['planned' => 'Planificado', 'active' => 'Activo'])" required help="Cierra o reabre el ciclo mediante las acciones específicas en la lista de ciclos." />
                @elseif($kind === 'subject')
                    <x-form-field name="name" label="Nombre de la materia" :value="$record->name" required maxlength="255" />
                    <x-form-field name="active" label="Estado del catálogo" :value="(int) $record->active" :options="[1 => 'Activa', 0 => 'Inactiva']" required help="Una materia inactiva conserva sus grupos existentes." />
                @elseif($kind === 'offering')
                    <x-form-field name="cycle_id" label="Ciclo académico" :value="old('cycle_id', $record->cycle_id ?? request('cycle_id'))" :options="['' => 'Selecciona un ciclo'] + $cycles->all()" required />
                    @if(!$record->exists)
                        <div class="mb-3"><label class="form-label" for="cycle_format_id">Formato de cómputos</label><select class="form-select" id="cycle_format_id" name="cycle_format_id" data-format-select><option value="">Sin formato: configurar cómputos por separado</option>@foreach($formats as $format)<option value="{{ $format->id }}" data-cycle="{{ $format->cycle_id }}" @selected((string) old('cycle_format_id', request('cycle_format_id')) === (string) $format->id)>{{ $format->cycle->code }} · {{ $format->name }} ({{ $format->periods->count() }} cómputos)</option>@endforeach</select><div class="form-hint">Selecciona un formato del ciclo elegido. Sus fechas se cargarán automáticamente.</div></div>
                    @elseif($record->cycle_format_id)<p class="form-hint mb-3">Este grupo conserva el calendario del formato {{ $record->cycleFormat->name }}.</p>@endif
                    <x-form-field name="subject_id" label="Materia" :value="$record->subject_id" :options="['' => 'Selecciona una materia'] + $subjects->all()" required />
                    <x-form-field name="teacher_id" label="Docente responsable" :value="$record->teacher_id" :options="['' => 'Selecciona un docente activo'] + $teachers->all()" required />
                    <div class="row"><div class="col-md-6"><x-form-field name="group_code" label="Código de grupo" :value="$record->group_code" required maxlength="20" /></div><div class="col-md-6"><x-form-field name="capacity" label="Cupo de estudiantes" type="number" :value="$record->capacity" required min="1" max="10000" /></div></div>
                    <p class="form-hint mb-3">Si el grupo ya tiene registros, su ciclo, materia y código se conservan. Las inscripciones futuras también reservan cupo.</p>
                @elseif($kind === 'period')
                    <x-form-field name="number" label="Número de cómputo" type="number" :value="$record->number" required min="1" max="99" />
                    <div class="row"><div class="col-md-6"><x-form-field name="starts_on" label="Fecha inicial" type="date" :value="$record->starts_on?->toDateString()" required :min="$offering->cycle->starts_on->toDateString()" :max="$offering->cycle->ends_on->toDateString()" /></div><div class="col-md-6"><x-form-field name="ends_on" label="Fecha final" type="date" :value="$record->ends_on?->toDateString()" required :min="$offering->cycle->starts_on->toDateString()" :max="$offering->cycle->ends_on->toDateString()" /></div></div>
                    <p class="form-hint mb-3">Los cómputos del mismo grupo no pueden superponerse. Consulta los cierres y sus versiones en los resultados del grupo.</p>
                @elseif($kind === 'enrollment')
                    @if($record->exists)<div class="mb-3"><span class="form-label d-block">Estudiante</span><strong>{{ $record->student->name }}</strong><div class="text-secondary">{{ $record->student->student_code }}</div></div>
                    @else<x-form-field name="student_id" label="Estudiante" :options="['' => 'Selecciona un estudiante activo'] + $students->all()" required />@endif
                    <div class="row"><div class="col-md-6"><x-form-field name="active_from" label="Inicio de vigencia" type="date" :value="$record->active_from?->toDateString()" required :min="$offering->cycle->starts_on->toDateString()" :max="$offering->cycle->ends_on->toDateString()" /></div><div class="col-md-6"><x-form-field name="active_to" label="Último día de vigencia" type="date" :value="$record->active_to?->toDateString()" :min="$offering->cycle->starts_on->toDateString()" :max="$offering->cycle->ends_on->toDateString()" help="Vacío: sin retiro. La fecha de retiro también cuenta como día de vigencia." /></div></div>
                @endif
                @if($record->exists)<x-form-field name="reason" label="Motivo del cambio" type="textarea" maxlength="1000" help="Obligatorio al cambiar fechas, estado, vigencia, cupo o docente." />@endif
                <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $record->exists ? 'Guardar cambios' : 'Crear '.$labels[$kind] }}</button><a class="btn btn-outline-secondary" href="{{ $back }}">Cancelar</a></div>
            </fieldset>
        </form>
    </div></div></div><div class="col-lg-4"><aside class="card"><div class="card-body"><span class="eyebrow">DATOS CON HISTORIA</span><h2 class="h3">Organización que perdura</h2><p class="text-secondary">Los registros relacionados se conservan. Los cambios sensibles requieren un motivo y quedan en la bitácora.</p><p class="mb-0 text-secondary">Las fechas de cómputos e inscripciones deben quedar dentro del ciclo académico.</p></div></aside></div></div>
@endsection
