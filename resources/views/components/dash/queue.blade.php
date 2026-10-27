@props(['label', 'count', 'href', 'icon' => 'info', 'tone' => 'clay', 'hint' => null])
@php
    $tones = ['clay' => 'bg-brand-50 text-brand-700', 'rose' => 'bg-danger-50 text-danger-700', 'gold' => 'bg-warning-100 text-warning-700', 'leaf' => 'bg-success-50 text-success-700', 'sky' => 'bg-info-50 text-info-700'];
@endphp
<a href="{{ $href }}" {{ $attributes->class(['group flex items-center gap-3 rounded-xl border px-3.5 py-3 transition hover:border-line-strong hover:bg-paper', 'border-line' => $count > 0, 'border-dashed border-line opacity-70' => $count === 0]) }}>
    <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $count > 0 ? ($tones[$tone] ?? $tones['clay']) : 'bg-sand text-subtle' }}"><x-ui.icon :name="$icon" class="size-[18px]" /></span>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-medium">{{ $label }}</span>
        @if ($hint)<span class="block truncate text-xs text-muted">{{ $hint }}</span>@endif
    </span>
    <span @class(['font-display text-lg font-semibold tabular-nums', 'text-subtle' => $count === 0])>{{ number_format($count) }}</span>
    <x-ui.icon name="chevron-right" class="size-4 text-subtle transition group-hover:translate-x-0.5" />
</a>
