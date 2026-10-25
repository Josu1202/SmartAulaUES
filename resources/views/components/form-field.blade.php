@props(['name', 'label', 'value' => null, 'type' => 'text', 'options' => null, 'help' => '', 'required' => false, 'id' => null, 'scope' => null])
@php
    $fieldId = $id ?? ($scope ? substr($scope, 0, 12).'-'.$name : $name);
    $fieldValue = \App\Support\FormState::old($scope, $name, $value);
    $invalid = \App\Support\FormState::submitted($scope) && $errors->has($name);
    if ($type === 'password' && $name !== 'current_password') {
        $help = trim($help.' Si la contraseña es muy larga, usa una más corta.');
    }
@endphp
<div class="mb-3">
    <label class="form-label" for="{{ $fieldId }}">{{ $label }}@if($required) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @if($options !== null)
        <select id="{{ $fieldId }}" name="{{ $name }}" class="form-select {{ $invalid ? 'is-invalid' : '' }}" @required($required) @if($help) aria-describedby="{{ $fieldId }}-help" @endif {{ $attributes }}>
            @foreach($options as $key => $caption)<option value="{{ $key }}" @selected((string) $fieldValue === (string) $key)>{{ $caption }}</option>@endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" class="form-control {{ $invalid ? 'is-invalid' : '' }}" rows="3" @required($required) @if($help) aria-describedby="{{ $fieldId }}-help" @endif {{ $attributes }}>{{ $fieldValue }}</textarea>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" class="form-control {{ $invalid ? 'is-invalid' : '' }}" @if(!in_array($type, ['password', 'file'])) value="{{ $fieldValue }}" @endif @required($required) @if($help) aria-describedby="{{ $fieldId }}-help" @endif {{ $attributes }}>
    @endif
    @if($help)<div class="form-hint" id="{{ $fieldId }}-help">{{ $help }}</div>@endif
    @if($invalid)<div class="invalid-feedback d-block">{{ $errors->first($name) }}</div>@endif
</div>
