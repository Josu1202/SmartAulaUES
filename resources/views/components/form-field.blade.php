@props(['name', 'label', 'value' => null, 'type' => 'text', 'options' => null, 'help' => '', 'required' => false, 'id' => null])
@php($fieldId = $id ?? $name)
<div class="mb-3">
    <label class="form-label" for="{{ $fieldId }}">{{ $label }}@if($required) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @if($options !== null)
        <select id="{{ $fieldId }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror" @required($required) @if($help) aria-describedby="{{ $fieldId }}-help" @endif {{ $attributes }}>
            @foreach($options as $key => $caption)<option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $caption }}</option>@endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" rows="3" @required($required) @if($help) aria-describedby="{{ $fieldId }}-help" @endif {{ $attributes }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" class="form-control @error($name) is-invalid @enderror" @if(!in_array($type, ['password', 'file'])) value="{{ old($name, $value) }}" @endif @required($required) @if($help) aria-describedby="{{ $fieldId }}-help" @endif {{ $attributes }}>
    @endif
    @if($help)<div class="form-hint" id="{{ $fieldId }}-help">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
