@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null])
<label class="block text-xs font-medium text-muted">
    {{ $label }}
    <select name="{{ $name }}" class="mt-1 block rounded-lg border border-line-strong bg-white py-1.5 pr-8 pl-3 text-sm text-ink" onchange="this.form.submit()">
        <option value="">{{ $placeholder ?? __('All') }}</option>
        @foreach ($options as $key => $optionLabel)
            <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</label>
