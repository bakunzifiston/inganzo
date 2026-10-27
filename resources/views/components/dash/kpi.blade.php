@props([
    'label',
    'value',
    'icon' => 'chart',
    'tone' => 'neutral',
    'hint' => null,
    'delta' => null,        // percentage change vs the previous period (int|float|null)
    'deltaLabel' => null,
    'invert' => false,      // true when a decrease is good (e.g. refunds)
    'href' => null,
])
@php
    $tones = [
        'neutral' => 'bg-sand text-ink-soft',
        'clay' => 'bg-brand-100 text-brand-700',
        'leaf' => 'bg-success-100 text-success-700',
        'gold' => 'bg-warning-100 text-warning-700',
        'sky' => 'bg-info-50 text-info-700',
        'rose' => 'bg-danger-50 text-danger-700',
    ];
    $deltaGood = $delta !== null && ($invert ? $delta <= 0 : $delta >= 0);
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['group flex min-w-0 flex-col rounded-2xl border border-line bg-white p-4 shadow-[var(--shadow-card)] sm:p-5', 'transition hover:-translate-y-0.5 hover:border-line-strong hover:shadow-[var(--shadow-lift)]' => $href]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium text-muted sm:text-sm">{{ $label }}</p>
        <span class="hidden size-9 shrink-0 place-items-center rounded-xl sm:grid {{ $tones[$tone] ?? $tones['neutral'] }}"><x-ui.icon :name="$icon" class="size-[18px]" /></span>
    </div>
    <p class="mt-2 truncate font-display text-xl leading-tight font-semibold tracking-tight tabular-nums sm:text-[1.65rem]">{{ $value }}</p>
    <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        @if ($delta !== null)
            <span @class(['inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-semibold', 'bg-success-50 text-success-700' => $deltaGood, 'bg-danger-50 text-danger-700' => ! $deltaGood])>
                <span aria-hidden="true">{{ $delta >= 0 ? '▲' : '▼' }}</span>
                <span>{{ ($delta >= 0 ? '+' : '').number_format($delta, abs($delta) < 10 ? 1 : 0) }}%</span>
                <span class="sr-only">{{ $deltaGood ? __('improvement') : __('decline') }}</span>
            </span>
            <span class="hidden text-subtle sm:inline">{{ $deltaLabel ?? __('vs previous period') }}</span>
        @endif
        @if ($hint)<span class="text-subtle">{{ $hint }}</span>@endif
    </div>
</{{ $tag }}>
