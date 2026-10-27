@props(['label', 'value', 'total', 'tone' => 'clay', 'display' => null, 'href' => null])
@php
    $percent = $total > 0 ? min(100, round($value / $total * 100)) : 0;
    $bars = ['clay' => 'bg-brand-600', 'leaf' => 'bg-success-600', 'gold' => 'bg-warning-500', 'rose' => 'bg-danger-700', 'sky' => 'bg-info-700', 'ink' => 'bg-ink'];
@endphp
<div {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-3 text-sm">
        @if ($href)<a href="{{ $href }}" class="truncate font-medium hover:text-brand-700">{{ $label }}</a>@else<span class="truncate font-medium">{{ $label }}</span>@endif
        <span class="shrink-0 tabular-nums text-muted">{{ $display ?? number_format($value) }}</span>
    </div>
    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-sand" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $label }}">
        <div class="h-full rounded-full {{ $bars[$tone] ?? $bars['clay'] }}" style="width: {{ $percent }}%"></div>
    </div>
</div>
