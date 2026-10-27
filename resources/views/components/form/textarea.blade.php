@props(['label' => null, 'name', 'value' => null, 'hint' => null, 'required' => false, 'rows' => 4])
<x-form.field :label="$label" :name="$name" :hint="$hint" :required="$required" :class="$attributes->get('class')">
    <textarea name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->except(['class', 'id'])->class(['input', 'input-error' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
</x-form.field>
