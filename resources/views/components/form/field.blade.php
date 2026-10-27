@props(['label' => null, 'name', 'hint' => null, 'required' => false])
@php $errorKey = str_replace(['[', ']'], ['.', ''], $name); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $attributes->get('id', $name) }}" class="label">{{ $label }}@if ($required)<span class="text-brand-700"> *</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($hint && ! $errors->has($errorKey))<p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>@endif
    @error($errorKey)<p class="mt-1.5 text-xs font-medium text-danger-700">{{ $message }}</p>@enderror
</div>
