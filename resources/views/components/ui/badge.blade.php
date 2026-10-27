@props(['status' => null, 'tone' => null])
@php
    $tone = $tone ?? ($status instanceof \App\Enums\HasBadge ? $status->tone() : 'neutral');
    $label = $status instanceof \App\Enums\HasBadge ? $status->label() : null;
    $tones = [
        'neutral' => 'bg-sand text-ink-soft ring-line-strong',
        'info' => 'bg-info-50 text-info-700 ring-info-700/20',
        'success' => 'bg-success-50 text-success-700 ring-success-600/25',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-700/25',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-700/20',
        'accent' => 'bg-brand-50 text-brand-700 ring-brand-600/25',
    ];
    $dots = [
        'neutral' => 'bg-subtle', 'info' => 'bg-info-700', 'success' => 'bg-success-600',
        'warning' => 'bg-warning-700', 'danger' => 'bg-danger-700', 'accent' => 'bg-brand-600',
    ];
@endphp
<span {{ $attributes->class('inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$tones[$tone]) }}>
    <span class="size-1.5 rounded-full {{ $dots[$tone] }}" aria-hidden="true"></span>
    {{ $label ?? $slot }}
</span>
