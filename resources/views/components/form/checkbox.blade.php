@props(['label', 'name', 'checked' => false, 'value' => '1', 'hint' => null])
<div {{ $attributes->only('class') }}>
    <label class="flex cursor-pointer items-start gap-3 text-sm">
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
            {{ $attributes->except('class')->class('mt-0.5 size-4 rounded border-line-strong text-brand-700 accent-brand-600 focus:ring-brand-100') }}>
        <span>
            <span class="font-medium text-ink">{{ $label }}</span>
            @if ($hint)<span class="mt-0.5 block text-xs text-muted">{{ $hint }}</span>@endif
        </span>
    </label>
    @error($name)<p class="mt-1.5 text-xs font-medium text-danger-700">{{ $message }}</p>@enderror
</div>
