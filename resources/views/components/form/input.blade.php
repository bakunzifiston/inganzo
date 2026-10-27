@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false])
@php $errorKey = str_replace(['[', ']'], ['.', ''], $name); @endphp
<x-form.field :label="$label" :name="$name" :hint="$hint" :required="$required" :class="$attributes->get('class')" :id="$attributes->get('id', $name)">
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $attributes->get('id', $name) }}"
        @if ($type !== 'password' && $type !== 'file') value="{{ old($errorKey, $value) }}" @endif
        @required($required)
        {{ $attributes->except(['class', 'id'])->class(['input', 'input-error' => $errors->has($errorKey)]) }}>
</x-form.field>
