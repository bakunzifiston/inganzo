@props(['label' => null, 'name', 'options' => [], 'value' => null, 'hint' => null, 'required' => false, 'placeholder' => null])
@php $selected = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value); @endphp
<x-form.field :label="$label" :name="$name" :hint="$hint" :required="$required" :class="$attributes->get('class')">
    <select name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @required($required)
        {{ $attributes->except(['class', 'id'])->class(['input pr-9', 'input-error' => $errors->has($name)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-form.field>
