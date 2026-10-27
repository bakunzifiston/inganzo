@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'href' => null])
@php
    $accent = ['default' => 'text-ink', 'clay' => 'text-brand-700', 'leaf' => 'text-success-700', 'warning' => 'text-warning-700', 'danger' => 'text-danger-700'][$tone];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class('card block p-5 transition hover:shadow-[var(--shadow-lift)]') }}>
@else
    <div {{ $attributes->class('card p-5') }}>
@endif
        <p class="text-sm font-medium text-muted">{{ $label }}</p>
        <p class="mt-2 font-display text-2xl font-semibold tracking-tight {{ $accent }}">{{ $value }}</p>
        @if ($hint)<p class="mt-1 text-xs text-subtle">{{ $hint }}</p>@endif
@if ($href)
    </a>
@else
    </div>
@endif
